<?php
/**
 * Property overview box on the listing edit screen.
 *
 * @package CRC_Real_Estate
 */

namespace CRC\RealEstate\Admin;

use CRC\RealEstate\Post_Type;
use CRC\RealEstate\Sections\Overview;
use CRC\RealEstate\Sections\Price_Card;
use CRC\RealEstate\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Lets editors fill in the four main details, the ready-made details for the
 * See More popup, which depend on the category, and groups of their own.
 */
final class Overview_Box {

	const NONCE   = 'crc_overview_nonce';
	const FIELD   = 'crc_overview';
	const DETAILS = 'crc_details';
	const EXTRA   = 'crc_overview_extra';
	const SHOWN   = 'crc_overview_shown';

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
		$groups   = Overview::groups( $post->ID );
		$extras   = Overview::extras( $post->ID );
		$suggest  = $this->suggestions();
		$category = $this->category( $post );

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
			<p class="description"><?php esc_html_e( 'These show only in the See More popup, below the four boxes, each with a check mark. The ready-made groups change with the category chosen in the Category box, and each one has Add detail for details of your own, which show after its other details. Fill in what applies to this listing: a detail left empty doesn\'t show on the site, and neither does a group with nothing filled in. See More shows on the listing page once there is something for the popup.', 'crc-real-estate' ); ?></p>
			<p class="description crc-overview-box-no-category"<?php echo $category ? ' hidden' : ''; ?>><?php esc_html_e( 'Choose a category in the Category box to see the details for it.', 'crc-real-estate' ); ?></p>

			<?php foreach ( Overview::common_groups() as $key => $group ) : ?>
				<?php
				$terms  = $this->term_ids( $group['categories'] );
				$active = ! $group['categories'] || in_array( $category, $terms, true );
				?>
				<div class="crc-overview-box-section" data-categories="<?php echo esc_attr( $group['categories'] ? implode( ' ', $terms ) : 'all' ); ?>"<?php echo $active ? '' : ' hidden'; ?>>
					<input type="hidden" name="<?php echo esc_attr( self::SHOWN . '[]' ); ?>" value="<?php echo esc_attr( $key ); ?>"<?php echo $active ? '' : ' disabled'; ?>>
					<h4 class="crc-overview-box-subtitle"><?php echo esc_html( $group['title'] ); ?></h4>
					<div class="crc-fields crc-overview-box-common">
						<?php
						foreach ( $group['items'] as $name => $item ) {
							$this->common_field( $post, $key, $name, $item, ! $active );
						}
						?>
					</div>
					<ul class="crc-overview-box-details crc-overview-box-extras" data-group="<?php echo esc_attr( $key ); ?>">
						<?php
						foreach ( isset( $extras[ $key ] ) ? $extras[ $key ] : array() as $d => $item ) {
							$this->extra( $key, $d, $item, ! $active );
						}
						?>
					</ul>
					<p class="crc-overview-box-group-foot"><button type="button" class="button crc-overview-box-extra-add"<?php echo $active ? '' : ' disabled'; ?>><?php esc_html_e( 'Add detail', 'crc-real-estate' ); ?></button></p>
				</div>
			<?php endforeach; ?>

			<h4 class="crc-overview-box-subtitle"><?php esc_html_e( 'Your own groups', 'crc-real-estate' ); ?></h4>
			<p class="description"><?php esc_html_e( 'For anything else, add a group with a title and its details, for example "Nearby places" with Nearest town and Nearest school. They show after the groups above. Drag the groups and details to change their order.', 'crc-real-estate' ); ?></p>

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
			<template class="crc-overview-box-extra-template"><?php $this->extra( '{g}', '{d}', array( 'label' => '', 'value' => '' ), false ); ?></template>
			<?php
			$this->datalist( 'crc-overview-box-titles', $suggest['titles'] );
			$this->datalist( 'crc-overview-box-labels', $suggest['labels'] );
			?>
		</div>
		<?php
	}

	/**
	 * The listing's category, as a term ID.
	 *
	 * @param \WP_Post $post Listing being edited.
	 * @return int 0 when none is chosen.
	 */
	private function category( $post ) {
		$terms = wp_get_object_terms( $post->ID, Taxonomy::NAME, array( 'fields' => 'ids' ) );

		return ( ! is_wp_error( $terms ) && $terms ) ? (int) $terms[0] : 0;
	}

	/**
	 * Term IDs for category slugs.
	 *
	 * @param string[] $slugs Category slugs.
	 * @return int[]
	 */
	private function term_ids( array $slugs ) {
		$ids = array();

		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, Taxonomy::NAME );

			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
			}
		}

		return $ids;
	}

	/**
	 * Prints a ready-made detail's field. A linked detail gets a locked
	 * field with its value, which is changed where it comes from.
	 *
	 * @param \WP_Post $post     Listing being edited.
	 * @param string   $group    Group name, which keeps field ids unique.
	 * @param string   $name     Detail name.
	 * @param array    $item     Detail from Overview::common_groups().
	 * @param bool     $disabled Whether the group is for another category.
	 */
	private function common_field( $post, $group, $name, array $item, $disabled ) {
		$id = 'crc-detail-' . str_replace( '_', '-', $group . '-' . $name );
		?>
		<div class="crc-field-group">
			<p class="crc-field">
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $item['label'] ); ?></label>
				<?php
				if ( 'linked' === $item['type'] ) {
					$this->locked( $post, $id, $name, $item );
				} else {
					$this->control( $post, $id, $name, $item, $disabled );
				}
				?>
			</p>
			<?php if ( 'linked' === $item['type'] && '' !== $item['note'] ) : ?>
				<p class="description" id="<?php echo esc_attr( $id . '-note' ); ?>"><?php echo esc_html( $item['note'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Prints the locked field of a linked detail, e.g. Price per perch from
	 * the Price box. It shows the value like the other fields, but can't be
	 * typed in and isn't saved from here.
	 *
	 * @param \WP_Post $post Listing being edited.
	 * @param string   $id   Field id.
	 * @param string   $name Detail name.
	 * @param array    $item Linked detail from Overview::common_groups().
	 */
	private function locked( $post, $id, $name, array $item ) {
		$money = is_callable( $item['amount'] );
		$value = Overview::detail_text( $post->ID, $item );

		if ( $money ) {
			$amount = Overview::linked_amount( $post->ID, $item );
			$value  = '' !== $amount ? number_format_i18n( (float) $amount ) : '';
		}

		echo '<span class="crc-measure crc-locked">';

		if ( $money ) {
			printf( '<span class="crc-money-currency">%s</span>', esc_html( Price_Card::currency() ) );
		}

		printf(
			'<input type="text" id="%1$s" value="%2$s" placeholder="%3$s" data-crc-linked="%4$s" readonly%5$s>',
			esc_attr( $id ),
			esc_attr( $value ),
			esc_attr__( 'Not set', 'crc-real-estate' ),
			esc_attr( $name ),
			'' !== $item['note'] ? ' aria-describedby="' . esc_attr( $id . '-note' ) . '"' : ''
		);

		$after = $money ? trim( sprintf( $item['format'], '' ) ) : '';

		if ( '' !== $after ) {
			printf( '<span class="crc-measure-unit">%s</span>', esc_html( $after ) );
		}

		printf( '<span class="dashicons dashicons-lock crc-locked-icon" title="%s" aria-hidden="true"></span>', esc_attr__( 'Locked', 'crc-real-estate' ) );
		echo '</span>';
	}

	/**
	 * Prints the input for a ready-made detail: a number with its unit, an
	 * amount, a list to choose from, or a text box. Fields of groups for
	 * another category are switched off, so they aren't saved.
	 *
	 * @param \WP_Post $post     Listing being edited.
	 * @param string   $id       Field id.
	 * @param string   $name     Detail name.
	 * @param array    $item     Detail from Overview::common_groups().
	 * @param bool     $disabled Whether the field is switched off.
	 */
	private function control( $post, $id, $name, array $item, $disabled ) {
		$field = self::DETAILS . '[' . $name . ']';
		$value = (string) get_post_meta( $post->ID, $item['meta'], true );
		$off   = $disabled ? ' disabled' : '';

		if ( 'select' === $item['type'] ) {
			printf( '<select id="%1$s" name="%2$s" class="crc-select"%3$s>', esc_attr( $id ), esc_attr( $field ), $off ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed attribute.
			printf( '<option value="">%s</option>', esc_html__( '— Select —', 'crc-real-estate' ) );

			foreach ( $item['options'] as $key => $label ) {
				printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $key ), selected( $value, (string) $key, false ), esc_html( $label ) );
			}

			echo '</select>';
			return;
		}

		if ( 'money' === $item['type'] ) {
			$amount = Price_Card::sanitize_amount( $value );
			$after  = trim( sprintf( $item['format'], '' ) );

			echo '<span class="crc-measure">';
			printf( '<span class="crc-money-currency">%s</span>', esc_html( Price_Card::currency() ) );
			printf( '<input type="text" inputmode="numeric" id="%1$s" name="%2$s" value="%3$s" autocomplete="off"%4$s>', esc_attr( $id ), esc_attr( $field ), esc_attr( '' !== $amount ? number_format_i18n( (float) $amount ) : '' ), $off ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed attribute.

			if ( '' !== $after ) {
				printf( '<span class="crc-measure-unit">%s</span>', esc_html( $after ) );
			}

			echo '</span>';
			return;
		}

		if ( 'number' !== $item['type'] ) {
			printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" autocomplete="off"%4$s>', esc_attr( $id ), esc_attr( $field ), esc_attr( $value ), $off ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed attribute.
			return;
		}

		$number = Overview::sanitize_number( $value, $item['decimals'] );

		echo '<span class="crc-measure">';
		printf(
			'<input type="text" inputmode="%1$s" id="%2$s" name="%3$s" value="%4$s" autocomplete="off"%5$s>',
			$item['decimals'] ? 'decimal' : 'numeric',
			esc_attr( $id ),
			esc_attr( $field ),
			esc_attr( '' !== $number ? Overview::format_number( $number, null ) : '' ),
			$off // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed attribute.
		);

		if ( $item['units'] ) {
			$unit = (string) get_post_meta( $post->ID, $item['meta'] . '_unit', true );

			/* translators: %s: detail name, e.g. "Land extent". */
			printf( '<select name="%1$s" aria-label="%2$s"%3$s>', esc_attr( self::DETAILS . '[' . $name . '_unit]' ), esc_attr( sprintf( __( '%s unit', 'crc-real-estate' ), $item['label'] ) ), $off ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed attribute.

			foreach ( $item['units'] as $key => $noop ) {
				printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $key ), selected( $unit, (string) $key, false ), esc_html( Overview::unit_name( $noop ) ) );
			}

			echo '</select>';
		} elseif ( $item['unit'] ) {
			printf( '<span class="crc-measure-unit">%s</span>', esc_html( Overview::unit_name( $item['unit'] ) ) );
		}

		echo '</span>';
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
				<input type="text" name="<?php echo esc_attr( $name . '[title]' ); ?>" value="<?php echo esc_attr( $group['title'] ); ?>" class="crc-overview-box-group-title" list="crc-overview-box-titles" placeholder="<?php esc_attr_e( 'Group title, for example Utilities', 'crc-real-estate' ); ?>" aria-label="<?php esc_attr_e( 'Group title', 'crc-real-estate' ); ?>" autocomplete="off">
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
	 * Prints one detail of the listing's own groups.
	 *
	 * @param int|string $g    Index of the group.
	 * @param int|string $d    Index of the detail.
	 * @param array      $item Detail with 'label' and 'value'.
	 */
	private function detail( $g, $d, array $item ) {
		$this->row( self::FIELD . '[' . $g . '][items][' . $d . ']', $item, false );
	}

	/**
	 * Prints one detail added to a ready-made group.
	 *
	 * @param string     $group    Ready-made group name.
	 * @param int|string $d        Index of the detail.
	 * @param array      $item     Detail with 'label' and 'value'.
	 * @param bool       $disabled Whether the group is for another category.
	 */
	private function extra( $group, $d, array $item, $disabled ) {
		$this->row( self::EXTRA . '[' . $group . '][' . $d . ']', $item, $disabled );
	}

	/**
	 * Prints a detail row: drag handle, label, value and remove button.
	 *
	 * @param string $name     Field name the label and value go under.
	 * @param array  $item     Detail with 'label' and 'value'.
	 * @param bool   $disabled Whether the fields are switched off.
	 */
	private function row( $name, array $item, $disabled ) {
		$off = $disabled ? ' disabled' : '';
		?>
		<li class="crc-overview-box-detail">
			<span class="crc-overview-box-detail-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'crc-real-estate' ); ?>" aria-hidden="true"></span>
			<input type="text" name="<?php echo esc_attr( $name . '[label]' ); ?>" value="<?php echo esc_attr( $item['label'] ); ?>" list="crc-overview-box-labels" placeholder="<?php esc_attr_e( 'Label, for example Water supply', 'crc-real-estate' ); ?>" aria-label="<?php esc_attr_e( 'Label', 'crc-real-estate' ); ?>" autocomplete="off"<?php echo $off; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed attribute. ?>>
			<input type="text" name="<?php echo esc_attr( $name . '[value]' ); ?>" value="<?php echo esc_attr( $item['value'] ); ?>" placeholder="<?php esc_attr_e( 'Value, for example Pipe-borne', 'crc-real-estate' ); ?>" aria-label="<?php esc_attr_e( 'Value', 'crc-real-estate' ); ?>" autocomplete="off"<?php echo $off; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed attribute. ?>>
			<button type="button" class="crc-overview-box-detail-remove" aria-label="<?php esc_attr_e( 'Remove this detail', 'crc-real-estate' ); ?>"<?php echo $off; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed attribute. ?>><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
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

			foreach ( Overview::extras( $id ) as $items ) {
				foreach ( $items as $item ) {
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
	 * Saves the main details, the ready-made details with the details added
	 * to them, and the listing's own groups. Empty ones are removed.
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

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is cleaned by Overview::sanitize_detail() and sanitize_unit().
		$details = isset( $_POST[ self::DETAILS ] ) && is_array( $_POST[ self::DETAILS ] ) ? wp_unslash( $_POST[ self::DETAILS ] ) : array();

		foreach ( Overview::common_items() as $name => $item ) {
			// Only details that were on the screen: groups for another category send nothing.
			if ( 'linked' === $item['type'] || ! array_key_exists( $name, $details ) ) {
				continue;
			}

			$value = Overview::sanitize_detail( $item, $details[ $name ] );

			if ( '' !== $value ) {
				update_post_meta( $post_id, $item['meta'], wp_slash( $value ) );
			} else {
				delete_post_meta( $post_id, $item['meta'] );
			}

			if ( ! $item['units'] ) {
				continue;
			}

			// The unit is kept only with a number.
			$unit = '' !== $value ? Overview::sanitize_unit( $item, isset( $details[ $name . '_unit' ] ) ? $details[ $name . '_unit' ] : '' ) : '';

			if ( '' !== $unit ) {
				update_post_meta( $post_id, $item['meta'] . '_unit', $unit );
			} else {
				delete_post_meta( $post_id, $item['meta'] . '_unit' );
			}
		}

		// Details added to the ready-made groups: groups that were on the screen get what was sent; the others keep theirs.
		$shown = isset( $_POST[ self::SHOWN ] ) ? array_map( 'sanitize_key', array_filter( (array) wp_unslash( $_POST[ self::SHOWN ] ), 'is_scalar' ) ) : array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cleaned by sanitize_extras().
		$posted = isset( $_POST[ self::EXTRA ] ) && is_array( $_POST[ self::EXTRA ] ) ? wp_unslash( $_POST[ self::EXTRA ] ) : array();
		$extras = Overview::extras( $post_id );

		foreach ( $shown as $group ) {
			$extras[ $group ] = isset( $posted[ $group ] ) ? $posted[ $group ] : array();
		}

		$extras = Overview::sanitize_extras( $extras );

		if ( $extras ) {
			update_post_meta( $post_id, Overview::EXTRA_META, wp_slash( $extras ) );
		} else {
			delete_post_meta( $post_id, Overview::EXTRA_META );
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
