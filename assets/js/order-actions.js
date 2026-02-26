jQuery(function($){
  // Ensure our order action buttons open in a new tab (prevents losing the order list context)
  $('a.wc-action-button-wcposm_invoice_pdf, a.wc-action-button-wcposm_packing_pdf')
    .attr('target','_blank')
    .attr('rel','noopener');
});
