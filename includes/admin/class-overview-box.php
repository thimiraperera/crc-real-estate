<?php
/**
 * Property overview box on the listing edit screen.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Sections\Overview;

defined( 'ABSPATH' ) || exit;

/**
 * Lets editors fill in the four main details, and add more details in groups
 * for the See More popup.
 */
final class Overview_Box {

	const NONCE = 'crc_overview_nonce';
	const FIELD = 'crc_overview';

	/**
	 * How many recent listings to look through for suggestions.
	 */
	const SUGGEST_FROM = 50;

	/**
	 * Registers hooks.
	 */
	public function hooks() {
		add_action( 'add_meta_boxes_' . Post_Type::NAME, array( $this, 'add' ) );
		add_action( 'save_post_' . Post_Type::NAME, array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Adds the Property overview box.
	 */
	public function add() {
		add_meta_box( 'crc_listing_overview', __( 'Property overview', 'crc-real-estate' ), array( $this, 'render' ), Post_Type::NAME, 'normal', 'high' );
	}

	/**
	 * Loads the box's script on the listing screen.
	 *
	 * @param string $hook Current admin page.
	 */
	public function assets( $hook ) {
		$screen = get_current_screen();

		if ( ! $screen || Post_Type::NAME !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_script( 'crc-re-admin-overview', CRC_RE_URL . 'assets/js/admin-overview.js', array( 'jquery', 'jquery-ui-sortable' ), CRC_RE_VERSION, true );
		wp_localize_script(
			'crc-re-admin-overview',
			'crcReOverview',
			array(
				'confirmRemove' => __( 'Remove this group and all its details?', 'crc-real-estate' ),
			)
		);
	}

	/**
	 * Prints the Property overview box.
	 *
	 * @param \WP_Post $post Listing being edited.
	 */
	public function render( $post ) {
		$groups  = Overview::groups( $post->ID );
		$suggest = $this->suggestions();

		// An empty group to start from. Empty groups aren't saved.
		if ( ! $groups ) {
			$groups = array( $this->empty_group() );
		}

		wp_nonce_field( 'crc_overview_save', self::NONCE );
		?>
		<div class="crc-overview-box">
			<p class="description crc-overview-box-intro"><?php esc_html_e( 'These four show as boxes on the listing page, and again at the top of the See More popup. Pick a suggestion or type your own. Leave one empty to hide its box.', 'crc-real-estate' ); ?></p>

			<div class="crc-fields">
				<?php foreach ( Overview::fields() as $name => $field ) : ?>
					<?php $id = 'crc-' . str_replace( '_', '-', $name ) . '-field'; ?>
					<div class="crc-field-group">
						<p class="crc-field">
							<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
							<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( 'crc_' . $name ); ?>" value="<?php echo esc_attr( get_post_meta( $post->ID, $field['meta'], true ) ); ?>" list="<?php echo esc_attr( $id . '-list' ); ?>" class="regular-text" autocomplete="off">
						</p>
						<?php $this->datalist( $id . '-list', $suggest[ $name ] ); ?>
						<p class="description"><?php echo esc_html( $field['help'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>

			<h4 class="crc-overview-box-title"><?php esc_html_e( 'See More popup', 'crc-real-estate' ); ?></h4>
			<p class="description"><?php esc_html_e( 'These show only in the See More popup, below the four boxes, each with a check mark. Put related details in a group with a title, for example "Size and price" with Land extent, Extent in perches and Price per perch. Drag the groups and details to change their order. See More shows on the listing page once there is a detail here.', 'crc-real-estate' ); ?></p>

			<div class="crc-overview-box-groups">
				<?php
				foreach ( $groups as $g => $group ) {
					$this->group( $g, $group );
				}
				?>
			</div>
			<p><button type="button" class="button crc-overview-box-group-add"><?php esc_html_e( 'Add group', 'crc-real-estate' ); ?></button></p>

			<template class="crc-overview-box-group-template"><?php $this->group( '{g}', $this->empty_group( '{d}' ) ); ?></template>
			<template class="crc-overview-box-detail-template"><?php $this->detail( '{g}', '{d}', array( 'label' => '', 'value' => '' ) ); ?></template>
			<?php
			$this->datalist( 'crc-overview-box-titles', $suggest['titles'] );
			$this->datalist( 'crc-overview-box-labels', $suggest['labels'] );
			?>
		</div>
		<?php
	}

	/**
	 * A group with one empty detail.
	 *
	 * @param int|string $d Index of the detail.
	 * @return array
	 */
	private function empty_group( $d = 0 ) {
		return array(
			'title' => '',
			'items' => array(
				$d => array(
					'label' => '',
					'value' => '',
				),
			),
		);
	}

	/**
	 * Prints a group: its title, its details and its buttons.
	 *
	 * @param int|string $g     Index of the group.
	 * @param array      $group Group with 'title' and 'items'.
	 */
	private function group( $g, array $group ) {
		$name = self::FIELD . '[' . $g . ']';
		?>
		<div class="crc-overview-box-group" data-group="<?php echo esc_attr( $g ); ?>">
			<div class="crc-overview-box-group-head">
				<span class="crc-overview-box-group-handle dashicons dashicons-move" title="<?php esc_attr_e( 'Drag to reorder', 'crc-real-estate' ); ?>" aria-hidden="true"></span>
				<input type="text" name="<?php echo esc_attr( $name . '[title]' ); ?>" value="<?php echo esc_attr( $group['title'] ); ?>" class="crc-overview-box-group-title" list="crc-overview-box-titles" placeholder="<?php esc_attr_e( 'Group title, for example Size and price', 'crc-real-estate' ); ?>" aria-label="<?php esc_attr_e( 'Group title', 'crc-real-estate' ); ?>" autocomplete="off">
				<button type="button" class="button-link button-link-delete crc-overview-box-group-remove"><?php esc_html_e( 'Remove group', 'crc-real-estate' ); ?></button>
			</div>
			<ul class="crc-overview-box-details">
				<?php
				foreach ( $group['items'] as $d => $item ) {
					$this->detail( $g, $d, $item );
				}
				?>
			</ul>
			<p class="crc-overview-box-group-foot"><button type="button" class="button crc-overview-box-detail-add"><?php esc_html_e( 'Add detail', 'crc-real-estate' ); ?></button></p>
		</div>
		<?php
	}

	/**
	 * Prints one detail: its label and value.
	 *
	 * @param int|string $g    Index of the group.
	 * @param int|string $d    Index of the detail.
	 * @param array      $item Detail with 'label' and 'value'.
	 */
	private function detail( $g, $d, array $item ) {
		$name = self::FIELD . '[' . $g . '][items][' . $d . ']';
		?>
		<li class="crc-overview-box-detail">
			<span class="crc-overview-box-detail-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'crc-real-estate' ); ?>" aria-hidden="true"></span>
			<input type="text" name="<?php echo esc_attr( $name . '[label]' ); ?>" value="<?php echo esc_attr( $item['label'] ); ?>" list="crc-overview-box-labels" placeholder="<?php esc_attr_e( 'Label, for example Land extent', 'crc-real-estate' ); ?>" aria-label="<?php esc_attr_e( 'Label', 'crc-real-estate' ); ?>" autocomplete="off">
			<input type="text" name="<?php echo esc_attr( $name . '[value]' ); ?>" value="<?php echo esc_attr( $item['value'] ); ?>" placeholder="<?php esc_attr_e( 'Value, for example 20 Acres', 'crc-real-estate' ); ?>" aria-label="<?php esc_attr_e( 'Value', 'crc-real-estate' ); ?>" autocomplete="off">
			<button type="button" class="crc-overview-box-detail-remove" aria-label="<?php esc_attr_e( 'Remove this detail', 'crc-real-estate' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
		</li>
		<?php
	}

	/**
	 * Prints a list of suggestions for text fields.
	 *
	 * @param string   $id     List id.
	 * @param string[] $values Suggestions.
	 */
	private function datalist( $id, array $values ) {
		echo '<datalist id="' . esc_attr( $id ) . '">';

		foreach ( $values as $value ) {
			echo '<option value="' . esc_attr( $value ) . '"></option>';
		}

		echo '</datalist>';
	}

	/**
	 * Suggestions for every field: the built-in ones, then the ones used on
	 * recently edited listings.
	 *
	 * @return array[] Field name, 'titles' or 'labels' => values.
	 */
	private function suggestions() {
		$fields  = Overview::fields();
		$more    = Overview::suggestions();
		$suggest = array(
			'titles' => $more['titles'],
			'labels' => $more['labels'],
		);

		foreach ( $fields as $name => $field ) {
			$suggest[ $name ] = $field['suggestions'];
		}

		$ids = get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => 'any',
				'posts_per_page' => self::SUGGEST_FROM,
				'orderby'        => 'modified',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		if ( $ids ) {
			update_meta_cache( 'post', $ids );
		}

		foreach ( $ids as $id ) {
			foreach ( $fields as $name => $field ) {
				$suggest[ $name ][] = (string) get_post_meta( $id, $field['meta'], true );
			}

			foreach ( Overview::groups( $id ) as $group ) {
				$suggest['titles'][] = $group['title'];

				foreach ( $group['items'] as $item ) {
					$suggest['labels'][] = $item['label'];
				}
			}
		}

		foreach ( $suggest as $key => $values ) {
			$values          = array_filter( array_map( 'trim', $values ), 'strlen' );
			$suggest[ $key ] = array_values( array_unique( $values ) );
		}

		return $suggest;
	}

	/**
	 * Saves the main details and the See More groups. Empty ones are removed.
	 *
	 * @param int $post_id Listing ID.
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), 'crc_overview_save' ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( Overview::fields() as $name => $field ) {
			$key   = 'crc_' . $name;
			$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';

			if ( '' !== $value ) {
				update_post_meta( $post_id, $field['meta'], wp_slash( $value ) );
			} else {
				delete_post_meta( $post_id, $field['meta'] );
			}
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cleaned by sanitize_groups().
		$groups = isset( $_POST[ self::FIELD ] ) ? Overview::sanitize_groups( wp_unslash( $_POST[ self::FIELD ] ) ) : array();

		if ( $groups ) {
			update_post_meta( $post_id, Overview::MORE_META, wp_slash( $groups ) );
		} else {
			delete_post_meta( $post_id, Overview::MORE_META );
		}
	}
}
