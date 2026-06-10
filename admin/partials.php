<?php
defined( 'ABSPATH' ) || exit;

function seo_outreach_header( string $title, string $icon = 'dashicons-admin-generic', string $action_label = '', string $action_url = '' ): void {
?>
<div class="wrap seo-outreach-wrap">
<div class="seo-header">
  <div class="seo-header-left">
    <span class="dashicons <?= esc_attr( $icon ) ?>"></span>
    <div>
      <h1><?= esc_html( $title ) ?></h1>
      <p class="seo-brand">Haris Farooq &mdash; AI Driven SEO Expert</p>
    </div>
  </div>
  <?php if ( $action_label && $action_url ): ?>
  <a href="<?= esc_url( $action_url ) ?>" class="seo-btn seo-btn-primary">
    <span class="dashicons dashicons-controls-play"></span> <?= esc_html( $action_label ) ?>
  </a>
  <?php endif; ?>
</div>
<?php
}

function seo_outreach_footer(): void {
    echo '</div><!-- .seo-outreach-wrap -->';
}

function seo_outreach_notice( string $msg, string $type = 'success' ): void {
    echo '<div class="seo-notice seo-notice-' . esc_attr( $type ) . '">' . esc_html( $msg ) . '</div>';
}

function seo_outreach_badge( string $status ): string {
    $map = [
        'success' => 'success', 'sent' => 'success',
        'error'   => 'error',   'failed' => 'error',
        'info'    => 'info',    'running' => 'warning',
        'warning' => 'warning', 'complete' => 'success',
    ];
    $cls = $map[ strtolower( $status ) ] ?? 'info';
    return '<span class="seo-badge seo-badge-' . $cls . '">' . esc_html( ucfirst( $status ) ) . '</span>';
}
