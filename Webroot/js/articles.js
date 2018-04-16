$("button.comments").click(function(){

    var div = $(this).parent().next();

    console.log(div.css("display"))
    
    if(div.is(":hidden")){
        div.removeClass("d-none");
    } else {
        div.addClass("d-none");
    }
});