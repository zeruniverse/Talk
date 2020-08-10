async function get_msg()
{
    if($('#p').val()=='' || $('#m').val()=='')
    {
        alert('No passphrase or message detected.');
        return ;
    }
    $('#bu').html('Please Wait...');
    $('#bu').attr('disabled','disabled');
    var strmsg=escape_msg($('#m').val());
    var key=$('#p').val();
    var ph=$('#phint').val();
    var enc_key = await PBKDF2_SHA512(key);
    var post_key = await PBKDF2_SHA512(enc_key, 101);
    var post_msg = await AESCBC256Encrypt(strmsg, enc_key);

    $.post('sendms.php',
        {'p': post_key,
         'm': post_msg,
         'ph':ph,
         'typem':typem}, function(msg){
    if(msg.length>5 && msg.length<11) window.location.href="./send.php?code="+msg; else
    {
        alert('Oops, error happens! Please retry!');
        $('.bu').html('Generate Message Link');
        $('.bu').removeAttr('disabled');
    }
    });
}

async function get_msg(code)
{
    if($('#pcc').val()=='') {
        alert('No passphrase detected.');
        return ;
    }
    $('#bu').html('Please Wait...');
    $('#bu').attr('disabled','disabled');
    var key = await PBKDF2_SHA512($('#pcc').val());
    var post_key = await PBKDF2_SHA512(key, 101);

    $.post('getms.php',
        {'p':post_key,
         'm':code},
         async function(msg){
        if(msg=='0')
        {
            alert('Some error happens, may be caused by wrong passphrase. This page will be refreshed.');
            window.location.reload(true);
        }else
        {
            var kc= await AESCBC256Decrypt(msg, key);
            kc='<span class="colorred">Your message is:</span><br /><br /><br />' +
               '<pre id="maincontent">' + kc + '</pre><br /><br />' +
               '<a class="btn btn-md btn-success" href="index.php#sendmes">Send my message</a>';
            $('#showarea').html(kc);
        }
        });
}

$(function(){
    $("#bu").on('click', function() {get_msg($("#bu").attr("postcode"))});
});