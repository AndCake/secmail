(function($, _){
  var originalContent = $("#main").html();
  var mailboxes = [];

  var composeMsg = function(){
    $("#main").html(_.template($("script[data-template-id='new-msg']").html(), {}));
    $("#main #send-to").fcbkcomplete({
      json_url: "data.php?addressbook",
      cache: true,
      filter_case: false,
      newel: true
    });
    $("#main .newmsg form").submit(function(event){
      event.preventDefault();
      $.post(this.action, $(this).serialize(), function(data) {
        alert("Message sent.");
        location.hash = "#/";
      });
    });
  };

  var loadSetup = function() {
    $.getJSON("data.php?serverlist").then(function(data) {
      $("#main").html(_.template($("script[data-template-id='setup']").html(), {servers: data}));
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
            doRoute();
          });
          renderMailboxes(JSON.parse(data));
        });
      });
    });
  };

  var addContact = function() {
    $.getJSON("data.php?me", function(data){
      $("#main").html(_.template($("script[data-template-id='add-contact']").html(), {data: data.me}));
      qrcode.callback = function(a) {
        var lines = [];
        if (a.indexOf("-----BEGIN PUBLIC KEY-----") === 0) {
          // found a valid public key
          lines = a.split("\n");
        }
        $("#result").append(_.template($("script[data-template-id='renderContact']").html(), {line: lines[lines.length - 1], data: a}))
      };
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

  var renderMailboxes = function(data) {
    mailboxes = data;
    $("#main").html(originalContent);
    $("#mailboxes").html(_.template($("script[data-template-id='mailboxes']").html(), {data: data}));
    $("#mailboxes li a").click(function(event) {
/*
      renderMails(_.where(data, {id: $(this).data('id')})[0].messages);
      $("#mailboxes li").removeClass("active");
      $(this.parentNode).addClass("active"); */
    });
  };

  var renderMails = function(messages, mb) {
    if (messages) {
      console.log(messages);
      $("#main").html(_.template($("script[data-template-id='" + (mb == "requests" ? mb : "mails") + "']").html(), {messages: messages}));
    }
  }

  var showMailbox = function(mb) {
    if (mailboxes.length <= 0) {
      indexPage(function() { showMailbox(mb) });
    } else {
      renderMails(_.where(mailboxes, {id: mb[0]})[0].messages, mb[0]);
      $("#mailboxes li").removeClass("active");
      $("#mailboxes li a[data-id="+mb[0]+"]").parent().addClass("active");
    }
  }

  var indexPage = function(callback) {
    $.getJSON("data.php").then(function(data){
      if (data.error == "setup required") {
        location.hash = "#/setup";
      } else {
        renderMailboxes(data);
        if (typeof(callback) === "function") {
          callback();
        }
      }
    });
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
    url: /\/mb\/([a-zA-Z]+)/,
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
    doRoute();
  }
}(jQuery, _));