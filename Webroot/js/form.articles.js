//rafraîchir l'image de l'article dès que l'on sélectionne une nouvelle image
$(function(){
    $('#path_image').change(function(){
        var input = this;
        var url = $(this).val();
        var ext = url.substring(url.lastIndexOf('.') + 1).toLowerCase();
        if (input.files && input.files[0]&& (ext == "gif" || ext == "png" || ext == "jpeg" || ext == "jpg"))
        {
            var reader = new FileReader();

            reader.onload = function (e) {
                $('#img').attr('src', e.target.result);
            };
            reader.readAsDataURL(input.files[0]);
        }
    });

    $("#tags > div:last span").click(function(){
        $( "<span class='badge badge-light'>" + $(this).text() + "</span>").appendTo($('#tags > div:first'));
        $(this).remove();
        if($("#tags > div:last span").length == 0){
            $("#tags > div:last").remove();
        }
        $("#tags > div:first span").click(function(){
            $(this).remove();
        });
    });

    $("#tags > div:first span").click(function(){
        $(this).remove();
    });

    $('#tags button').click(function(){
        $( "<span class='badge badge-light'>" + $('#tags input').val() + "</span>" ).appendTo($('#tags > div:first'));
        $("#tags input").val('');
    });

    $(":submit").on("click", function(e){

        if($("form")[0].checkValidity()){
            e.preventDefault();
    
            var list = new Array();

            $("#tags > div:first span").each(function(){
                list.push($(this).html());
            });

            var formData = new FormData();
            formData.append('title', $("#title").val());
            formData.append('content', $("#content").val());
            formData.append("path_image", $('#path_image')[0].files[0]);
            formData.append('id_category', $("#id_category").val());
            formData.append('tags', list);
            
            $.ajax({
                type : 'POST',
                url: $(location).attr('href'),
                data: formData,
                processData: false,
                contentType: false,
                success: function(result) {
                    if(result === "Creation successfull" || result === "Edition successfull"){
                        $( "<div class='alert alert-success' role='alert'>" + result + "</div>" ).insertBefore($(':submit'));
                        var delay = 2000; 
                        setTimeout(function(){
                        $('.alert').remove();
                        window.location.replace($(location).attr('href'));
                        }, delay);
                    } else {
                        $( "<div class='alert alert-danger' role='alert'>" + result + "</div>" ).insertBefore($(':submit'));
                    }
                
                },
                error:function(result) {
                    $( "<div class='alert alert-danger' role='alert'>Server Error</div>" ).insertBefore($(':submit'));
                }
            });
        }
    });
    
});