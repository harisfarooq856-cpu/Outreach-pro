<?php
// admin/page-reports.php
defined('ABSPATH')||exit;
require_once SEO_OUTREACH_DIR.'admin/partials.php';
$pdf_dir = SEO_Outreach_PDF_Generator::get_pdf_dir();
$pdfs    = [];
if(is_dir($pdf_dir)){
  foreach(glob($pdf_dir.'*.pdf')as $f){
    $pdfs[]=['name'=>basename($f),'size'=>round(filesize($f)/1024,1),'time'=>filemtime($f)];
  }
  usort($pdfs,fn($a,$b)=>$b['time']-$a['time']);
}
seo_outreach_header('PDF Reports','dashicons-media-document');
?>
<div class="seo-card"><div class="seo-card-body seo-p0">
<table class="seo-table">
  <thead><tr><th>Filename</th><th>Size</th><th>Generated</th><th>Actions</th></tr></thead>
  <tbody>
    <?php if(empty($pdfs)):?><tr><td colspan="4" class="seo-empty">No reports yet. Run a campaign to generate PDF reports.</td></tr>
    <?php else:foreach($pdfs as $p):?>
    <tr>
      <td><span class="dashicons dashicons-media-document" style="color:#ef4444;vertical-align:middle"></span> <?=esc_html($p['name'])?></td>
      <td><?=$p['size']?> KB</td>
      <td><?=date('Y-m-d H:i',$p['time'])?></td>
      <td><a href="<?=admin_url('admin-ajax.php?action=seo_outreach_download_pdf&file='.urlencode($p['name']).'&nonce='.wp_create_nonce('seo_outreach_nonce'))?>" class="seo-btn seo-btn-sm seo-btn-primary"><span class="dashicons dashicons-download"></span> Download</a></td>
    </tr>
    <?php endforeach;endif;?>
  </tbody>
</table>
</div></div>
<?php seo_outreach_footer();
