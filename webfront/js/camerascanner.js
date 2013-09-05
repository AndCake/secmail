var CameraScanner = (function(){
	var gCtx = null, gCanvas = null, imageData = null, c = 0, 
		stype = 0, gUM = false, webkit = false, moz = false,
		v = null, targetSelector = null, camstream = null,

		camhtml = '<object  id="iembedflash" classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=7,0,0,0" width="320" height="240"> '+
	  		'<param name="movie" value="images/camcanvas.swf" />'+
	  		'<param name="quality" value="high" />'+
			'<param name="allowScriptAccess" value="always" />'+
	  		'<embed  allowScriptAccess="always"  id="embedflash" src="camcanvas.swf" quality="high" width="320" height="240" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/go/getflashplayer" mayscript="true"  />'+
	    '</object>',

	    vidhtml = '<video id="v" autoplay></video>';

	function initCanvas() {
	    gCanvas = document.getElementById("qr-canvas");
	    gCtx = gCanvas.getContext("2d");
	    gCtx.clearRect(0, 0, 260, 260);
	    imageData = gCtx.getImageData( 0,0,260,260);
	}

	function captureToCanvas() {
	    if(stype != 1)
	        return;

	    if (gUM) {
	        try {
	        	if (v && gCtx) {
	            	gCtx.drawImage(v,0,0);
		            try {
		                qrcode.decode();
		                cancel();
		            } catch(e) {
		            	if (e.message.indexOf("alignment patterns") >= 0 || 
		            		e.message.indexOf("number of roots") >= 0 || 
		            		e.message == "Error") {
		            		$(v).addClass("almost");
		            	} else {
		            		$(v).removeClass("almost");
		            	}
		                window.console && console.log(e);
		                setTimeout(captureToCanvas, 500);
		            };
		        }
	        } catch(e) {
	                window.console && console.log(e);
	                setTimeout(captureToCanvas, 500);
	        };
	    } else {
	        flash = document.getElementById("embedflash");
	        try{
	            flash.ccCapture();
	        } catch(e) {
	            window.console && console.log(e);
	            setTimeout(captureToCanvas, 1000);
	        }
	    }
	}

	function isCanvasSupported(){
	  var elem = document.createElement('canvas');
	  return !!(elem.getContext && elem.getContext('2d'));
	}

	function success(stream) {
	    if (webkit) 
	    	v.src = window.webkitURL.createObjectURL(stream);
	    else if (moz) {
	        v.mozSrcObject = stream;
	        v.play();
	    } else
	        v.src = stream;

	    gUM = true;
		camstream = stream;
	    setTimeout(captureToCanvas, 500);
	}
			
	function error(error) {
	    gUM = false;
	    return;
	}

	return {
		scan: function(target) {
			initCanvas();
			targetSelector = target;
			document.getElementById("result").innerHTML="- scanning -";
		    if (stype == 1) {
		        setTimeout(captureToCanvas, 500);    
		        return;
		    }
		    var n = navigator;
		    if (n.getUserMedia) {
		        $(target).html(vidhtml);
		        v = document.getElementById("v");
		        n.getUserMedia({video: true, audio: false}, success, error);
		    } else if (n.webkitGetUserMedia) {
		        $(target).html(vidhtml);
		        v = document.getElementById("v");
		        webkit = true;
		        n.webkitGetUserMedia({video: true, audio: false}, success, error);
		    } else if(n.mozGetUserMedia) {
		        $(target).html(vidhtml);
		        v = document.getElementById("v");
		        moz = true;
		        n.mozGetUserMedia({video: true, audio: false}, success, error);
		    } else
  		        $(target).html(camhtml);

		    stype = 1;
		    setTimeout(captureToCanvas, 500);
		},

		cancel: function cancel() {
		    if (webkit) {
		    	camstream && camstream.stop();
		    	v.src = null;
		    } else if (moz) {
		        v.mozSrcObject = null;
		        v.stop();
		    } else {
		    	camstream && camstream.stop();
		        v.src = null;
		    }

			$(targetSelector).html("");
			stype = 2;
		}
	};
}());