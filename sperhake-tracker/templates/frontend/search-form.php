<?php
/**
 * Vehicle search form template.
 *
 * @package SperhakeTracker
 * @var array<string, mixed> $atts               Shortcode attributes (provides $atts['title']).
 * @var bool                 $recaptcha_enabled  Whether the CAPTCHA is active.
 * @var string               $recaptcha_site_key reCAPTCHA site key.
 * @var bool                 $require_reference  Whether the reference field is required.
 * @var string               $reference_label    Label for the reference field.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title              = isset( $atts['title'] ) ? (string) $atts['title'] : __( 'Find Your Vehicle', 'sperhake-tracker' );
$recaptcha_enabled  = ! empty( $recaptcha_enabled );
$recaptcha_site_key = isset( $recaptcha_site_key ) ? (string) $recaptcha_site_key : '';
$require_reference  = ! empty( $require_reference );
$reference_label    = isset( $reference_label ) ? (string) $reference_label : __( 'Case Number', 'sperhake-tracker' );
?>
<div class="sperhake-widget" id="sperhake-widget">
	<div class="sperhake-widget__header">
		<h2 class="sperhake-widget__title"><?php echo esc_html( $title ); ?></h2>
		<p class="sperhake-widget__subtitle">
			<?php esc_html_e( 'Enter your license plate to check your vehicle status and pay any outstanding penalty.', 'sperhake-tracker' ); ?>
		</p>
	</div>

	<form class="sperhake-search" id="sperhake-search-form" novalidate>
		<label class="sperhake-search__label" for="sperhake-plate">
			<?php esc_html_e( 'License Plate', 'sperhake-tracker' ); ?>
		</label>
		<div class="sperhake-search__row">
			<input
				type="text"
				id="sperhake-plate"
				name="license_plate"
				class="sperhake-search__input"
				placeholder="AB-CD-123"
				autocomplete="off"
				inputmode="latin"
				maxlength="32"
				required
			/>
			<?php if ( ! $require_reference ) : ?>
				<button type="submit" class="sperhake-btn sperhake-btn--primary" id="sperhake-search-btn">
					<?php esc_html_e( 'Search Vehicle', 'sperhake-tracker' ); ?>
				</button>
			<?php endif; ?>
		</div>

		<?php if ( $require_reference ) : ?>
			<label class="sperhake-search__label" for="sperhake-reference" style="margin-top:14px;">
				<?php echo esc_html( $reference_label ); ?>
			</label>
			<div class="sperhake-search__row">
				<input
					type="text"
					id="sperhake-reference"
					name="reference"
					class="sperhake-search__input"
					autocomplete="off"
					maxlength="64"
					required
				/>
				<button type="submit" class="sperhake-btn sperhake-btn--primary" id="sperhake-search-btn">
					<?php esc_html_e( 'Search Vehicle', 'sperhake-tracker' ); ?>
				</button>
			</div>
			<p class="sperhake-search__hint">
				<?php
				printf(
					/* translators: %s: reference field label */
					esc_html__( 'Your %s is printed on the notice left on or near your vehicle.', 'sperhake-tracker' ),
					esc_html( $reference_label )
				);
				?>
			</p>
		<?php endif; ?>

		<?php if ( $recaptcha_enabled ) : ?>
			<div
				class="g-recaptcha sperhake-recaptcha"
				data-sitekey="<?php echo esc_attr( $recaptcha_site_key ); ?>"
			></div>
		<?php endif; ?>

		<p class="sperhake-search__error" id="sperhake-search-error" role="alert" hidden></p>
	</form>

	<div class="sperhake-results" id="sperhake-results" aria-live="polite"></div>
</div>
