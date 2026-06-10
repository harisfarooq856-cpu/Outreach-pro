<?php
// admin/page-notifications.php
defined('ABSPATH')||exit;
require_once SEO_OUTREACH_DIR.'admin/partials.php';
$db    = new SEO_Outreach_Database();
$notifs= $db->get_notifications(50);
seo_outreach_header('Notifications','dashicons-bell');
?>
<div class="seo-card"><div class="seo-card-body seo-p0">
<table class="seo-table">
  <thead><tr><th>Time</th><th>Type</th><th>Subject</th><th>Sent To</th><th>Status</th></tr></thead>
  <tbody>
    <?php if(empty($notifs)):?><tr><td colspan="5" class="seo-empty">No notifications sent yet.</td></tr>
    <?php else:foreach($notifs as $n):?>
    <tr>
      <td><?=esc_html($n['created_at'])?></td>
      <td><?=seo_outreach_badge($n['type']??'info')?></td>
      <td><?=esc_html($n['subject'])?></td>
      <td><?=esc_html($n['sent_to'])?></td>
      <td><?=seo_outreach_badge($n['status'])?></td>
    </tr>
    <?php endforeach;endif;?>
  </tbody>
</table>
</div></div>
<?php seo_outreach_footer();
