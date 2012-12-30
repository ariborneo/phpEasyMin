

$(function(){

	$('.toggle_group').click(function(){
		//$('.group_input').toggleClass('visible');
		var a = $('.group_input:visible');
		var b = $('.group_input:hidden');


		a.hide().find('[name=group]').attr('name','group_hidden');
		b.show().find('[name=group_hidden]').attr('name','group');
	});



	$('a.jswarnings').toggle(function(){
		$(this).siblings('div.jswarnings').slideDown();
	},function(){
		$(this).siblings('div.jswarnings').slideUp();
	});


	var $tabs = $('#tabs');
	var $sections = $('.checkphphead');
	if( $sections.length > 1 ){
		$sections.each(function(i,elmnt){

			var $this = $(this);
			var $area = $this.parent();
			$area.addClass('tab_area');


			var $link = $('<a href="">'+$this.text()+'</a>')
				.click(function(evt){
					evt.preventDefault();
					$('.tab_area').hide();
					$('#tabs .active').removeClass('active');

					$area.show();
					$(this).addClass('active');
				})
				.appendTo($tabs);

			if( i > 0 ){
				$area.hide();
			}else{
				$link.addClass('active');
			}

		});
	}



})


