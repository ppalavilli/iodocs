(function() {

    // Storing common selections
    var allEndpoints = $('li.endpoint'),
        allEndpointsLength = allEndpoints.length,
        allMethodLists = $('ul.methods'),
        allMethodListsLength = allMethodLists.length;

    function listMethods(context) {
        var methodsList = $('ul.methods', context || null);

        for (var i = 0, len = methodsList.length; i < len; i++) {
            $(methodsList[i]).slideDown();
        }
    }

    $('#api_selector').change(function(e) {
    	window.location = $(this).val();
    });
	// for adding array of params 
    $(".repeat1").click(function () {

    	var srcDiv = $(this).parent().prev();
        var clonedDiv = srcDiv.clone(true);
        clonedDiv.attr('class', 'cloned');
        $(clonedDiv).insertBefore($(this).parent());

        var triggerElements = $('.comx', clonedDiv);
        for (var i = 0, len = triggerElements.length; i < len; i++) {
        	// Calling dc.makeTrigger explicitly since jQuery.clone does not copy 
        	// onclick handlers attached by the domcollapse library
        	// despite the clone(true) argument.
        	// Also, remove the cloned expand/collapse image that 
        	// dc.makeTrigger will add again        	
            $('a:first', triggerElements[i]).remove(); 
            dc.makeTrigger(triggerElements[i]);
        }
        
        
        // Change 'name' attribute of cloned elements
        var listName = srcDiv.children('div').children('span:first').text();
		var currentName = $("input:first", $(clonedDiv)).attr("name");        
		var nameMatch = new RegExp("\." + listName + "\\((\\d)\\)\.").exec(currentName);		
		if(nameMatch && nameMatch.length == 2) {
			var oldValue = listName + '(' + nameMatch[1] + ')';			
			var newValue = listName + '(' + ( parseInt(nameMatch[1]) + 1 ) + ')';
			
			var arr =  $(clonedDiv).find('*').each(function() {
				var elemName = $(this).attr('name');
				if(typeof(elemName) != "undefined") {
					$(this).attr('name', elemName.replace( oldValue, newValue));
				}
			});
		}

    }); 

    $(".remove1").click(function () {
        var target = $(this).parent().prev();        
        if(target.attr('class') === 'cloned')
        {
            target.remove();
        }
    }); 
   // array support ends
   //toggle optional input fields
   $('.optional').click(function(event) {
	   $('span', this).toggle();
	   $(this.parentNode.parentNode).find('#optional').not('.hideElem').each(function() {
		   $(this).toggleClass('hide');        	
	   })
       event.stopPropagation();
    })
    
    // Toggle show/hide of method details, form, and results
    $('li.method > div.title').click(function() {
      $('form', this.parentNode).slideToggle();
      $(this).find('.http-method').toggleClass('collapseSection').toggleClass('expandSection'); 
      $(this).find('.optional').each(function(){
            $(this).toggleClass('hide');
      })
      $('form > ul > li').removeClass('trigger');
      $('form > ul > li').addClass('expanded');
      $('form > ul > ul').removeClass('hide').toggleClass('show');
    });

    // Toggle an endpoint
    $('li.endpoint > h3.title span.name').click(function() {
        $('ul.methods', this.parentNode.parentNode).slideToggle();
        $(this.parentNode.parentNode).toggleClass('expanded')
    })

    // Toggle all endpoints
    $('#toggle-endpoints').click(function(event) {
        event.preventDefault();

        // Check for collapsed endpoints (hidden methods)
        var endpoints = $('ul.methods:not(:visible)'),
            endpointsLength = endpoints.length;

        if (endpointsLength > 0) {
            // Some endpoints are collapsed, expand them.
            for (var x = 0; x < endpointsLength; x++) {
                var methodsList = $(endpoints[x]);
                methodsList.slideDown();
                methodsList.parent().toggleClass('expanded', true)

            }
        } else {
            // All endpoints are expanded, collapse them
            var endpoints = $('ul.methods'),
                endpointsLength = endpoints.length;

            for (var x = 0; x < endpointsLength; x++) {
                var methodsList = $(endpoints[x]);
                methodsList.slideUp();
                methodsList.parent().toggleClass('expanded', false)
            }
        }

    })

    // Toggle all methods
    $('#toggle-methods').click(function(event) {
        event.preventDefault();

        var methodForms = $('ul.methods form:not(:visible)'), // Any hidden method forms
            methodFormsLength = methodForms.length;

        // Check if any method is not visible. If so, expand all methods.
        if (methodFormsLength > 0) {
            var methodLists = $('ul.methods:not(:visible)'), // Any hidden methods
            methodListsLength = methodLists.length;

            // First make sure all the hidden endpoints are expanded.
            for (var x = 0; x < methodListsLength; x++) {
                $(methodLists[x]).slideDown();
            }

            // Now make sure all the hidden methods are expanded.
            for (var y = 0; y < methodFormsLength; y++) {
                $(methodForms[y]).slideDown();
            }

        } else {
            // Hide all visible method forms
            var visibleMethodForms = $('ul.methods form:visible'),
                visibleMethodFormsLength = visibleMethodForms.length;

            for (var i = 0; i < visibleMethodFormsLength; i++) {
                $(visibleMethodForms[i]).slideUp();
            }
        }

        for (var z = 0; z < allEndpointsLength; z++) {
            $(allEndpoints[z]).toggleClass('expanded', true);
        }
    })

    // List methods for a particular endpoint.
    // Hide all forms if visible
    $('li.list-methods a').click(function(event) {
        event.preventDefault();

        // Make sure endpoint is expanded
        var endpoint = $(this).closest('li.endpoint'),
            methods = $('li.method form', endpoint);

        listMethods(endpoint);

        // Make sure all method forms are collapsed
        var visibleMethods = $.grep(methods, function(method) {
            return $(method).is(':visible')
        })

        $(visibleMethods).each(function(i, method) {
            $(method).slideUp();
        })

        $(endpoint).toggleClass('expanded', true);
        $('#endpoints').find('a.optional').each(function(){
            $(this).addClass('hide');
        })
        
        $('.http-method').addClass('collapseSection').removeClass('expandSection');
    })

    // Expand methods for a particular endpoint.
    // Show all forms and list all methods
    $('li.expand-methods a').click(function(event) {
        event.preventDefault();

        // Make sure endpoint is expanded
        var endpoint = $(this).closest('li.endpoint'),
            methods = $('li.method form', endpoint);

        listMethods(endpoint);

        // Make sure all method forms are expanded
        var hiddenMethods = $.grep(methods, function(method) {
            return $(method).not(':visible')
        })

        $(hiddenMethods).each(function(i, method) {
            $(method).slideDown();
        })

        $(endpoint).toggleClass('expanded', true);

        $('form > ul > li').removeClass('trigger');
        $('form > ul > li').addClass('expanded');
        $('form > ul > ul').removeClass('hide').toggleClass('show');           
        $('.optional').each(function(){
        
            $(this).addClass('hide').removeClass('show');
        })
        $('#endpoints').find('#optional').each(function(){
        	$(this).addClass('hide').removeClass('show');
        })
        $('a.optional').each(function(){
            $(this).removeClass('hide');
        })
       $('.http-method').removeClass('collapseSection').addClass('expandSection');
    });

    // Toggle headers section
    $('div.headers h4').click(function(event) {
        event.preventDefault();

        $(this.parentNode).toggleClass('expanded');

        $('div.fields', this.parentNode).slideToggle();
    });

    // Auth with OAuth
    $('#credentials').submit(function(event) {
        event.preventDefault();

        var params = $(this).serializeArray();

        $.post('/auth', params, function(result) {
            if (result.signin) {
                window.open(result.signin,"_blank","height=900,width=800,menubar=0,resizable=1,scrollbars=1,status=0,titlebar=0,toolbar=0");
            }
        })
    });

    /*
        Try it! button. Submits the method params, apikey and secret if any, and apiName
    */
    $('li.method form').submit(function(event) {
        var self = this;

        event.preventDefault();
        var apiMethodName = $('input[name=methodName]', $(this)).val();
        var httpMethod = $('input[name=httpMethod]', $(this)).val();
        
        var params = $(this).serializeArray(),
            apiKey = { name: 'apiKey', value: $('input[name=key]').val() },
            apiSecret = { name: 'apiSecret', value: $('input[name=secret]').val() },
            apiUserName = { name: 'userName', value: $('input[name=userName]').val() },
            apiPassword = { name: 'password', value: $('input[name=password]').val() },
            apiSignature = { name: 'signature', value: $('input[name=signature]').val() },
            appId = { name: 'appId', value: $('input[name=appId]').val() },
            apiName = { name: 'apiName', value: $('input[name=apiName]').val() },
            authorization = { name: 'authorization', value: $('input[name=authorization]').val() };

        params.push(apiKey, apiSecret, apiName, apiUserName, apiPassword, apiSignature, appId, authorization);
        // Process json value        
        if($(this).attr('enctype') == 'application/json') {
        	params.push({ name: 'params[jsonParam]', value: form2JSON(params)});
    	}
    
        // Setup results container
        var resultContainer = $('.result', self);
        if (resultContainer.length === 0) {
            resultContainer = $(document.createElement('div')).attr('class', 'result');
            $(self).append(resultContainer);
        }
        
        if ($('pre.response', resultContainer).length !== 0) {
        	resultContainer.empty();
        }

        //console.log(params);
        $("input[type=submit]", self).attr("disabled", "disabled");
        $.post('/processReq', params, function(result, text) {
            // If we get passed a signin property, open a window to allow the user to signin/link their account
            if (result.signin) {
                window.open(result.signin,"_blank","height=900,width=800,menubar=0,resizable=1,scrollbars=1,status=0,titlebar=0,toolbar=0");
            } else {
                var response,
                    responseContentType = result.headers['content-type'];
                // Format output according to content-type
                response = livedocs.formatData(result.response, result.headers['content-type'])

                $('pre.response', resultContainer)
                    .toggleClass('error', false)
                    .text(response);
            }

        })
        // Complete, runs on error and success
        .complete(function(result, text) {
            var response = JSON.parse(result.responseText);
            var idPrefix = apiMethodName.replace(/[{}\/]/g, "_") + httpMethod;
            var template_data = {reqbody_id: idPrefix + '-reqbody', reqheaders_id: idPrefix + '-reqheaders', respheaders_id: idPrefix + '-respheaders', 
            		respbody_id: idPrefix + '-respbody', endpoint_id: idPrefix + '-endpoint'};
            
            if (response.call) {
            	try {
	                template_data.reqbody = formatJSON(JSON.parse(response.call));  
            	} catch (e) {
            		template_data.reqbody = response.call;  
            	}            	
            } else {
            	template_data.reqbody = '';
            }
	        if (response.endPoint) {
	        	template_data.endpoint = response.endPoint;	        	
            }
            if (response.reqHeaders) {
            	template_data.reqheaders = formatJSON(response.reqHeaders);            	
            }
            if (response.headers) {
            	template_data.respheaders = formatJSON(response.headers);
            }           
            if (response.response.indexOf("<?xml") == -1) {
            	 template_data.respbody = formatJSON(JSON.parse(response.response));            	 
            }
            
            var template = Handlebars.compile($("#api-tryit-template").html());
            resultContainer.append(template(template_data));
            // Syntax highlighting
            prettyPrint();            
            $('.response_panel a', resultContainer).click(function (e) {
        	  e.preventDefault();
        	  $(this).tab('show');
        	});
            $('.response_panel .nav-tabs a:last', resultContainer).tab('show');
            $("input[type=submit]", self).removeAttr("disabled");    
        })
        .error(function(err, text) {
            var response;

            if (err.responseText !== '') {
                var result = JSON.parse(err.responseText),
                    headers = formatJSON(result.headers);

                if (result.headers && result.headers['content-type']) {
                    // Format the result.response and assign it to response
                    response = livedocs.formatData(result.response, result.headers['content-type']);
                } else {
                    response = result.response;
                }

            } else {
                response = 'Error';
            }

            $('div', resultContainer)
                .toggleClass('error', true)
                .text(response);
            $("input[type=submit]", self).removeAttr("disabled");
        })
    })

})();

function form2JSON(params) {	
	var filteredParams = [];
	var uriParts = [];
	for(var i in params) {
		if(params[i].name == 'methodUri') {
			uriParts = params[i].value.split('/');
		}
	}
	jQuery.map(params, function(n, i){
		if(n.name.indexOf("params[") != -1) {
			if(n.name.indexOf('.') != -1) {
				var k = n.name.substring(n.name.indexOf('.') + 1, n.name.length - 1);
			} else {
				var k = n.name.substring(n.name.indexOf('params[') + 7, n.name.length - 1);
			}
			if(jQuery.inArray(':' + k, uriParts) == -1) {
				filteredParams.push({'name': k, 'value': n.value});
			}
		}
	});
	
	jsonData = form2js(filteredParams);	
	return JSON.stringify(jsonData);
	
}

