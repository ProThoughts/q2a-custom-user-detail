$(document).ready(function(){
  $(document).on('click', "#favoriting a", function(){
    var ens = $(this).attr('name').split('_');
    var code = $(this).parent().data('code');
    
    qa_ajax_post('favorite', {entitytype:ens[1], entityid:ens[2], favorite:parseInt(ens[3]), code:code},
      function (lines) {
        if (lines[0]=='1') {
          qa_set_inner_html(document.getElementById('favoriting'), 'favoriting', lines.slice(1).join("\n"));
          componentHandler.upgradeDom();
        } else if (lines[0]=='0') {
          console.log(lines[1]);
        } else
          qa_ajax_error();
      }
    );
  });
});
