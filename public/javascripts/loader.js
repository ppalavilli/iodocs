var opts = {
              lines: 13, // The number of lines to draw
              length: 4, // The length of each line
              width: 9, // The line thickness
              radius: 33, // The radius of the inner circle
              corners: 1, // Corner roundness (0..1)
              rotate: 0, // The rotation offset
              color: '#000', // #rgb or #rrggbb
              speed: 1, // Rounds per second
              trail: 60, // Afterglow percentage
              shadow: false, // Whether to render a shadow
              hwaccel: false, // Whether to use hardware acceleration
              className: 'spinner', // The CSS class to assign to the spinner
              zIndex: 2e9, // The z-index (defaults to 2000000000)
              top: 'auto', // Top position relative to parent in px
              left: 'auto' // Left position relative to parent in px
            };
            var spinner = new Spinner(opts).spin();
            $('#endpoints').append(spinner.el);
            $('.spinner').css('margin-left', '300px');
           
//           for(var i=0; i<10000000000000; i++)
