function escape_msg(strmsg)
{
    strmsg=strmsg.split('&').join('&amp;');
    strmsg=strmsg.split('<').join('&lt;');
    strmsg=strmsg.split('>').join('&gt;');
    return strmsg;
}

async function send_msg(typem)
{
    if($('#p').val()=='' || $('#m').val()=='')
    {
        alert('No passphrase or message detected.');
        return ;
    }
    $('.bu').html('Please Wait...');
    $('.bu').attr('disabled','disabled');
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

$(function(){
    $("#bu").on('click', send_msg(0));
    $("#bu1").on('click', send_msg(1));
});