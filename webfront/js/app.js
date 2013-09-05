(function($, _){
  var originalContent = $("#main").html();
  var mailboxes = [];

  var composeMsg = function(msg) {
    data = {to: false, mailbox: null, subject: null, body: null, toName: null};
    if (msg) {
      console.log(mailboxes);
      message = _.where(_.where(mailboxes, {id: msg[0]})[0].messages, {id: msg[1]})[0];
      data.to = message.message.message.header.From.id;
      data.toName = message.message.message.header.From.name;
      data.subject = message.message.message.header.Subject;
      data.body = "<p></p><dl><dt>On " + message.message.message.header.Date + ", " + message.from + " wrote:</dt><dd>" + message.message.message.body + "</dd></dt>";
      data.mailbox = msg[0];
    }
    renderTpl("#main", 'new-msg', data);
    $("#main #send-to").fcbkcomplete({
      json_url: "data.php?addressbook",
      cache: true,
      filter_case: false
    });
    tinymce.init({
      selector: "#main .newmsg form textarea",
      plugins: [
          "advlist autolink lists link image charmap print preview anchor",
          "searchreplace visualblocks code fullscreen",
          "insertdatetime media table contextmenu paste"
      ],
      toolbar: "insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image",
      removed_menuitems: 'newdocument'
    });
    $("#main .newmsg form").submit(function(event){
      event.preventDefault();
      var send = [];
      $("*[name='send[to][]'] option[selected]").each(function(){
        send.push(encodeURIComponent("send[to][]") + "=" + encodeURIComponent(this.value));
      });
      $("*[name='send[to][]']")[0].disabled = true;
      $.post(this.action, $(this).serialize() + "&" + send.join("&"), function(data) {
        $("*[name='send[to][]']")[0].disabled = false;
        try {
          data = JSON.parse(data);
        } catch(e){};
        if (data.success) {
          alert("Message sent.");
          location.hash = "#/";
        } else {
          alert("There was a problem sending the mail.");
        }
      });
    });
  };

  var deleteMsg = function(msgData) {
    $.getJSON("data.php?delete&mb=" + msgData[0] + "&msgid=" + msgData[1], function(data){
      renderMails(data[msgData[0]].messages, msgData[0]);
    });
  }

  var loadSetup = function() {
    $.getJSON("data.php?serverlist").then(function(data) {
      renderTpl("#main", 'setup', {servers: data});
      $(".setup select").change(function(){
        if (this.value == 0) {
          $(".setup .other-option").removeClass("hidden");
        } else {
          $(".setup .other-option").addClass("hidden");
        }
      })
      $(".setup form").submit(function(event) {
        event.preventDefault();
        $.post(this.action, $(this).serialize(), function(data) {
          $("aside #new-msg").click(function(){
            location.hash = "#" + $(this).data("href");
          });
          renderMailboxes(JSON.parse(data));
        });
      });
    });
  };

  var addContact = function() {
    $.getJSON("data.php?me", function(data){
      renderTpl("#main", 'add-contact', {data: data.me});
      qrcode.callback = function(a) {
        $.post("data.php?getContact", {contact: a}, function(data) {
          data = JSON.parse(data);
          $("#btn-connect")[0].disabled = false;
          renderTpl("#result", 'renderContact', {line: data.name, data: a}, true);
          CameraScanner.cancel();
          $("#qrfile").show();
          $(".import .x-tabs li a.active").removeClass("active");
          $(".import .x-tabs li a[href$='#qrfile']").addClass("active");
        });
      };
      var scanning = false;
      $(".import ul.x-tabs a").click(function(event) {
        event.preventDefault();
        if (this.href.replace(/.*#/, '') == 'qrcam') {
          CameraScanner.scan("#qrcam");
          $(".import .x-tabs li a.active").removeClass("active");
          $(".import .x-tabs li a[href$='#qrcam']").addClass("active");
          $("#qrfile").hide();
        } else {
          CameraScanner.cancel();
          $("#qrfile").show();
          $(".import .x-tabs li a.active").removeClass("active");
          $(".import .x-tabs li a[href$='#qrfile']").addClass("active");
        }
      });
      var gCanvas = document.getElementById("qr-canvas");
      var w = 800;
      var h = 600;
      gCanvas.style.width = w + "px";
      gCanvas.style.height = h + "px";
      gCanvas.width = w;
      gCanvas.height = h;
      var gCtx = gCanvas.getContext("2d");
      gCtx.clearRect(0, 0, w, h);
      var imageData = gCtx.getImageData(0, 0, 320, 240);

      document.querySelector("#qrfile input").addEventListener("change", handleFiles = function(e){
        e.stopPropagation();
        e.preventDefault();

        var dt = e.dataTransfer || this;
        var files = dt.files;
        if (files.length > 0) {
          var o = [];
            
          for (var i = 0; i < files.length; i++) {
            var reader = new FileReader();
            reader.onload = (function(theFile) {
              return function(e) {
                gCtx.clearRect(0, 0, gCanvas.width, gCanvas.height);

                qrcode.decode(e.target.result);
              };
            }(files[i]));
            reader.readAsDataURL(files[i]);
          }
        } else if (dt.getData && dt.getData('URL')) {
          qrcode.decode(dt.getData('URL'));
        }
      }, false);

      $("#qrfile")[0].addEventListener("dragenter", function(e){
        e.stopPropagation();
        e.preventDefault();
      }, false)
      $("#qrfile")[0].addEventListener("dragover", function(e){
        e.stopPropagation();
        e.preventDefault();
      }, false)
      $("#qrfile")[0].addEventListener("drop", handleFiles, false);
      $("#main .mails form.import").submit(function(event){
        event.preventDefault();
        $.post(this.action, $(this).serialize(), function(data){
          data = JSON.parse(data);
          if (data.success) {
            alert("Contacts were successfully added to the addressbook.");
          }
        });
      });
    });
  }

  var renderMailboxes = function(data, noInit) {
    mailboxes = data;
    if (!noInit) $("#main").html(originalContent);
    renderTpl("#mailboxes", "mailboxes", {data: data});
  };

  var renderMails = function(messages, mb) {
    if (messages) {
      renderTpl("#main", "mails", {messages: messages});
      $("#main #mails li[data-msg-id]").click(function(event) {
        $("li", this.parentNode).removeClass("active");
        $(this).addClass("active");
        renderTpl("#main #maildetails", "maildetails", {mailbox: mb, message: _.where(messages, {id: $(this).data('msg-id')})[0]});
      });
    }
  }

  var showMailbox = function(mb) {
    if (mailboxes.length <= 0) {
      indexPage(function() { showMailbox(mb) });
    } else {
      renderMails(_.sortBy(_.where(mailboxes, {id: mb[0]})[0].messages, function(msg){
        return new Date(msg.message.message.header.Date).getTime() * -1;
      }), mb[0]);
      $("#mailboxes li").removeClass("active");
      $("#mailboxes li a[data-id="+mb[0]+"]").parent().addClass("active");
    }
  }

  var printCode = function() {
    window.print();
    location.hash = "#/connect";
  }

  var indexPage = function(callback, noInit) {
    $.getJSON("data.php" + (noInit ? "?fetch" : "")).then(function(data){
      if (data.error == "setup required") {
        location.hash = "#/setup";
      } else {
        renderMailboxes(data, noInit);
        if (typeof(callback) === "function") {
          callback();
        }
      }
    });
  }

  var renderTpl = function(target, name, data, append) {
    return $(target)[(append ? "append" : "html")](_.template($("script[data-template-id='" + name + "']").html(), data));
  }

  var doRoute = function() {
    var url = location.hash.replace("#", "");
    _.each(routes, function(entry) {
      if (typeof(entry.url) === "string") {
        if (url == entry.url) {
          entry.fn();
        }
      } else {
        if (match = url.match(entry.url)) {
          entry.fn(match.slice(1));
        }
      }
    })
  };

var routes = [{
    url: "/new",
    fn: composeMsg
  }, {
    url: /\/mb\/([a-zA-Z0-9_-]+)/,
    fn: showMailbox
  }, {
    url: "/setup",
    fn: loadSetup
  }, {
    url: "/",
    fn: indexPage
  }, {
    url: "/connect",
    fn: addContact
  }, {
    url: /\/(?:reply|forward)\/([a-zA-Z0-9_-]+)\/([0-9a-f\.]+)$/,
    fn: composeMsg
  }, {
    url: /\/delete\/([a-zA-Z0-9_-]+)\/([0-9a-f\.]+)$/,
    fn: deleteMsg
  }, {
    url: "/print",
    fn: printCode
  }];

  var oldHash = location.hash;
  setInterval(function(){
    if (oldHash != location.hash) {
      oldHash = location.hash;
      doRoute();
    }
  }, 250);
  if (location.hash.length <= 0) {
    location.hash = "#/";
  } else {
    indexPage(doRoute);
  }

  setTimeout(function() {
    setInterval(function() {
      indexPage(null, true);
    }, 300000);
  }, 60000);
}(jQuery, _));