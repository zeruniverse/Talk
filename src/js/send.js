$(function(){
    $("#cplink").on('click', function() {
        return navigator.clipboard.writeText($("#link").text())
                .catch(function() {
                    alert('Copy to clipboard failed. Please do this manually.');
                });
    });
});