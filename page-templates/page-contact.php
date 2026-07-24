<?php
/**
 * Template Name: Contact
 *
 * Contact details plus a working enquiry form. Submissions are emailed to
 * the admin address and stored as private enquiry posts so nothing is lost
 * if mail delivery hiccups. Honeypot spam guard, no plugin dependency.
 *
 * @package WebsitesGHShop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

$sent = isset( $_GET['sent'] ) && '1' === $_GET['sent'];
?>
<div class="wrap py-12 sm:py-16">
	<div class="grid grid-cols-1 gap-12 lg:grid-cols-2">
		<div>
			<header class="max-w-measure">
				<p class="eyebrow"><?php esc_html_e( 'Contact', 'wghshop' ); ?></p>
				<h1 class="mt-2 text-4xl sm:text-5xl font-extrabold leading-[1.08]"><?php the_title(); ?></h1>
			</header>
			<div class="wghs-prose mt-8"><?php the_post() && the_content(); ?></div>
		</div>

		<div>
			<?php if ( $sent ) : ?>
				<div class="rounded-lg border border-wgh-green bg-wgh-greenPale p-6">
					<p class="font-bold text-wgh-greenInk"><?php esc_html_e( 'Message received.', 'wghshop' ); ?></p>
					<p class="mt-1 text-sm text-wgh-greenInk"><?php esc_html_e( 'We reply within working hours, usually much faster on WhatsApp.', 'wghshop' ); ?></p>
				</div>
			<?php else : ?>
			<form class="card p-6 sm:p-8" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<h2 class="text-lg font-bold"><?php esc_html_e( 'Send us a message', 'wghshop' ); ?></h2>
				<input type="hidden" name="action" value="wghs_enquiry">
				<?php wp_nonce_field( 'wghs_enquiry' ); ?>
				<p class="hidden" aria-hidden="true"><label>Leave this empty<input type="text" name="wghs_hp" tabindex="-1" autocomplete="off"></label></p>
				<p class="mt-4"><label class="mb-1 block text-sm font-semibold" for="wghs_c_name"><?php esc_html_e( 'Your name', 'wghshop' ); ?></label>
					<input class="input" type="text" id="wghs_c_name" name="name" required></p>
				<p class="mt-4"><label class="mb-1 block text-sm font-semibold" for="wghs_c_phone"><?php esc_html_e( 'Phone number', 'wghshop' ); ?></label>
					<input class="input" type="tel" id="wghs_c_phone" name="phone" required></p>
				<p class="mt-4"><label class="mb-1 block text-sm font-semibold" for="wghs_c_msg"><?php esc_html_e( 'Message', 'wghshop' ); ?></label>
					<textarea class="input" id="wghs_c_msg" name="message" rows="5" required></textarea></p>
				<button class="btn-primary mt-6 w-full sm:w-auto" type="submit"><?php esc_html_e( 'Send message', 'wghshop' ); ?></button>
				<p class="mt-3 text-xs text-wgh-ink3"><?php esc_html_e( 'Faster answer needed? WhatsApp 054 214 8020.', 'wghshop' ); ?></p>
			</form>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php get_footer();
