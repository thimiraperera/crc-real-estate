<?php
/**
 * Property features box on the listing edit screen.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Sections\Features;

defined( 'ABSPATH' ) || exit;

/**
 * Lets editors tick the ready-made features for the listing's category, add
 * features of their own to those groups, and add groups of their own.
 */
final class Features_Box {

	const NONCE = 'crc_features_nonce';
	const TICKS = 'crc_features';
	const SHOWN = 'crc_features_shown';
	const EXTRA = 'crc_features_extra';
	const FIELD = 'crc_features_groups';

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
	 * Adds the Property features box.
	 */
	public function add() {
		add_meta_box( 'crc_listing_features', __( 'Property features', 'crc-real-estate' ), array( $this, 'render' ), Post_Type::NAME, 'normal', 'high' );
	}

	/**
	 * Loads the boxes' script on the listing screen.
	 *
	 * @param string $hook Current admin page.
	 */
	public function assets( $hook ) {
		if ( Boxes::on_listing_screen( $hook ) ) {
			Boxes::enqueue_script();
		}
	}

	/**
	 * Prints the Property features box.
	 *
	 * @param \WP_Post $post Listing being edited.
	 */
	public function render( $post ) {
		$category = Boxes::category( $post );
		$ticked   = Features::ticked( $post->ID );
		$extras   = Features::extras( $post->ID );
		$groups   = Features::groups( $post->ID );
		$suggest  = $this->suggestions();

		wp_nonce_field( 'crc_features_save', self::NONCE );
		?>
		<div class="crc-box crc-features-box">
			<p class="description crc-box-intro"><?php esc_html_e( 'Tick the features this listing has. They show with check marks: the first few on the listing page, and all of them in the See More popup. The ready-made groups change with the category chosen in the Category box, and each one has Add feature for features of your own, which show after its ticked ones.', 'crc-real-estate' ); ?></p>
			<p class="description crc-box-no-category"<?php echo $category ? ' hidden' : ''; ?>><?php esc_html_e( 'Choose a category in the Category box to see the features for it.', 'crc-real-estate' ); ?></p>

			<?php foreach ( Features::common_groups() as $key => $group ) : ?>
				<?php
				$active = Boxes::is_for( $group['categories'], $category );
				$off    = $active ? '' : ' disabled';
				?>
				<div class="crc-box-section" data-categories="<?php echo esc_attr( Boxes::categories_attr( $group['categories'] ) ); ?>"<?php echo $active ? '' : ' hidden'; ?>>
					<input type="hidden" name="<?php echo esc_attr( self::SHOWN . '[]' ); ?>" value="<?php echo esc_attr( $key ); ?>"<?php echo $off; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed attribute. ?>>
					<h4 class="crc-box-subtitle"><?php echo esc_html( $group['title'] ); ?></h4>
					<div class="crc-box-checks">
						<?php foreach ( $group['features'] as $name => $label ) : ?>
							<label class="crc-box-check">
								<input type="checkbox" name="<?php echo esc_attr( self::TICKS . '[]' ); ?>" value="<?php echo esc_attr( $name ); ?>"<?php checked( in_array( (string) $name, $ticked, true ), true ); ?><?php echo $off; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed attribute. ?>>
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</div>
					<ul class="crc-box-items crc-box-extras" data-group="<?php echo esc_attr( $key ); ?>">
						<?php
						foreach ( isset( $extras[ $key ] ) ? $extras[ $key ] : array() as $d => $label ) {
							$this->row( self::EXTRA . '[' . $key . '][' . $d . ']', $label, ! $active );
						}
						?>
					</ul>
					<p class="crc-box-group-foot"><button type="button" class="button crc-box-extra-add"<?php echo $off; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed attribute. ?>><?php esc_html_e( 'Add feature', 'crc-real-estate' ); ?></button></p>
				</div>
			<?php endforeach; ?>

			<h4 class="crc-box-subtitle"><?php esc_html_e( 'Your own groups', 'crc-real-estate' ); ?></h4>
			<p class="description"><?php esc_html_e( 'For anything else, add a group with a title and its features, for example "Religious places" with Close to the temple. They show after the groups above. Drag the groups and features to change their order.', 'crc-real-estate' ); ?></p>

			<div class="crc-box-groups">
				<?php
				foreach ( $groups as $g => $group ) {
					$this->group( $g, $group );
				}
				?>
			</div>
			<p><button type="button" class="button crc-box-group-add"><?php esc_html_e( 'Add group', 'crc-real-estate' ); ?></button></p>

			<template class="crc-box-group-template">
				<?php
				$this->group(
					'{g}',
					array(
						'title' => '',
						'items' => array( '{d}' => '' ),
					)
				);
				?>
			</template>
			<template class="crc-box-item-template"><?php $this->row( self::FIELD . '[{g}][items][{d}]', '', false ); ?></template>
			<template class="crc-box-extra-template"><?php $this->row( self::EXTRA . '[{g}][{d}]', '', false ); ?></template>
			<?php
			Boxes::datalist( 'crc-features-box-titles', $suggest['titles'] );
			Boxes::datalist( 'crc-features-box-labels', $suggest['labels'] );
			?>
		</div>
		<?php
	}

	/**
	 * Prints one of the listing's own groups: its title, its features and its
	 * buttons.
	 *
	 * @param int|string $g     Index of the group.
	 * @param array      $group Group with 'title' and 'items'.
	 */
	private function group( $g, array $group ) {
		$name = self::FIELD . '[' . $g . ']';
		?>
		<div class="crc-box-group" data-group="<?php echo esc_attr( $g ); ?>">
			<div class="crc-box-group-head">
				<span class="crc-box-group-handle dashicons dashicons-move" title="<?php esc_attr_e( 'Drag to reorder', 'crc-real-estate' ); ?>" aria-hidden="true"></span>
				<input type="text" name="<?php echo esc_attr( $name . '[title]' ); ?>" value="<?php echo esc_attr( $group['title'] ); ?>" class="crc-box-group-title" list="crc-features-box-titles" placeholder="<?php esc_attr_e( 'Group title, for example Religious places', 'crc-real-estate' ); ?>" aria-label="<?php esc_attr_e( 'Group title', 'crc-real-estate' ); ?>" autocomplete="off">
				<button type="button" class="button-link button-link-delete crc-box-group-remove"><?php esc_html_e( 'Remove group', 'crc-real-estate' ); ?></button>
			</div>
			<ul class="crc-box-items">
				<?php
				foreach ( $group['items'] as $d => $label ) {
					$this->row( $name . '[items][' . $d . ']', $label, false );
				}
				?>
			</ul>
			<p class="crc-box-group-foot"><button type="button" class="button crc-box-item-add"><?php esc_html_e( 'Add feature', 'crc-real-estate' ); ?></button></p>
		</div>
		<?php
	}

	/**
	 * Prints a feature row: drag handle, the feature and a remove button.
	 *
	 * @param string $name     Field name.
	 * @param string $label    The feature.
	 * @param bool   $disabled Whether the row is switched off.
	 */
	private function row( $name, $label, $disabled ) {
		$off = $disabled ? ' disabled' : '';
		?>
		<li class="crc-box-item">
			<span class="crc-box-item-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'crc-real-estate' ); ?>" aria-hidden="true"></span>
			<input type="text" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $label ); ?>" list="crc-features-box-labels" placeholder="<?php esc_attr_e( 'Feature, for example Close to the temple', 'crc-real-estate' ); ?>" aria-label="<?php esc_attr_e( 'Feature', 'crc-real-estate' ); ?>" autocomplete="off"<?php echo $off; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed attribute. ?>>
			<button type="button" class="crc-box-item-remove" aria-label="<?php esc_attr_e( 'Remove this feature', 'crc-real-estate' ); ?>"<?php echo $off; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed attribute. ?>><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
		</li>
		<?php
	}

	/**
	 * Group titles and features typed on recently edited listings.
	 *
	 * @return array[] 'titles' and 'labels'.
	 */
	private function suggestions() {
		$suggest = array(
			'titles' => array(),
			'labels' => array(),
		);

		foreach ( Boxes::recent_listings( self::SUGGEST_FROM ) as $id ) {
			foreach ( Features::groups( $id ) as $group ) {
				$suggest['titles'][] = $group['title'];
				$suggest['labels']   = array_merge( $suggest['labels'], $group['items'] );
			}

			foreach ( Features::extras( $id ) as $labels ) {
				$suggest['labels'] = array_merge( $suggest['labels'], $labels );
			}
		}

		return array_map( array( Boxes::class, 'unique' ), $suggest );
	}

	/**
	 * Saves the ticked features, the features added to the ready-made groups
	 * and the listing's own groups. Groups for another category, which weren't
	 * on the screen, keep what they had.
	 *
	 * @param int $post_id Listing ID.
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), 'crc_features_save' ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$common = Features::common_groups();
		$shown  = isset( $_POST[ self::SHOWN ] ) ? array_map( 'sanitize_key', array_filter( (array) wp_unslash( $_POST[ self::SHOWN ] ), 'is_scalar' ) ) : array();
		$shown  = array_values( array_intersect( $shown, array_keys( $common ) ) );

		// Ticks: the groups on the screen get what was ticked; the others keep theirs.
		$on_screen = array();

		foreach ( $shown as $key ) {
			$on_screen = array_merge( $on_screen, array_map( 'strval', array_keys( $common[ $key ]['features'] ) ) );
		}

		$posted = isset( $_POST[ self::TICKS ] ) ? Features::sanitize_names( (array) wp_unslash( $_POST[ self::TICKS ] ) ) : array();
		$ticked = Features::sanitize_names( array_merge( array_diff( Features::ticked( $post_id ), $on_screen ), array_intersect( $posted, $on_screen ) ) );

		$this->store( $post_id, Features::META, $ticked );

		// Features added to the ready-made groups, the same way.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cleaned by sanitize_extras().
		$added  = isset( $_POST[ self::EXTRA ] ) && is_array( $_POST[ self::EXTRA ] ) ? wp_unslash( $_POST[ self::EXTRA ] ) : array();
		$extras = Features::extras( $post_id );

		foreach ( $shown as $key ) {
			$extras[ $key ] = isset( $added[ $key ] ) ? $added[ $key ] : array();
		}

		$this->store( $post_id, Features::EXTRA_META, Features::sanitize_extras( $extras ) );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cleaned by sanitize_groups().
		$groups = isset( $_POST[ self::FIELD ] ) ? Features::sanitize_groups( wp_unslash( $_POST[ self::FIELD ] ) ) : array();

		$this->store( $post_id, Features::GROUPS_META, $groups );
	}

	/**
	 * Saves a list, or removes it when it's empty.
	 *
	 * @param int    $post_id Listing ID.
	 * @param string $key     Meta key.
	 * @param array  $value   What to save.
	 */
	private function store( $post_id, $key, array $value ) {
		if ( $value ) {
			update_post_meta( $post_id, $key, wp_slash( $value ) );
		} else {
			delete_post_meta( $post_id, $key );
		}
	}
}
