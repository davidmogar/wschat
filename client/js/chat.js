$(function() {
    var user = "";
    var uri = "ws://192.168.254.4:6050/server.php";

    $("#login input").keyup(function(event) {
        if ($("#login input").val().length < 3) {
            $("#login button").hide("slow");
        } else {
            if (event.which == 13) {
                connect();
            }
            $("#login button").show("slow");
        }
    });

    $("#login button").click(function() {
        if ($("#login input").val().length >= 3) {
            connect();
        }
    });

    $("#chat button").click(function() {
        send();
    });

    $("#chat input").keyup(function(event) {
        if (event.which == 13) {
            send();
        }
    });

    function connect() {
        user = $("#login input").val();

        $("#chatbox.box h1").text("Connecting...");

        /* Change to chat window */
        $("#login").slideUp("slow", function() {
            $("#chatbox.box").animate({
                width: "600px"
            }, 200, "linear", function() {
                $("#chat").slideDown("slow");

                /* Connect */
                websocket = new WebSocket(uri);

                websocket.onclose = function(event) {
                    $("#chatbox.box h1").text("Ooops! Connection closed");
                }

                websocket.onerror = function(event) {
                    $("#chatbox.box h1").text("Ooops! Something went wrong");
                }

                websocket.onmessage = function(event) {
                    var data = JSON.parse(event.data);
                    var type = data.type;
                    var message = data.message;
                    var user = data.user;

                    switch (type) {
                        case "usermsg":
                            $("#messages").append("<div class=\"message\"><span class=\"user\">" + user + ": </span>" + message + "</div>");
                            break;
                        case "system":
                            $("#messages").append("<div class=\"system\">" + message + "</div>");
                            break;
                    }

                    /* Move scrollbar */
                    $("#messages").animate({ scrollTop: $('#messages').prop("scrollHeight") }, "slow");
                }

                websocket.onopen = function(event) {
                    $("#chatbox h1").text("Welcome, " + user + ". Enjoy ;)");
                }
            });
        });
    }

    function send() {
        var message = $("#chat input").val();

        /* Clear input */
        $("#chat input").val("");

        if (message != "") {
            var data = {
                message: message,
                user: user
            };

            websocket.send(JSON.stringify(data));
        }
    }

    /* YouTube search overlay */

    $("button#youtube").click(function() {
        $(".overlay").fadeIn();
    });

    $("button#close").click(function() {
        $(".overlay").fadeOut();
    });

    $(".overlay .content").on("click", "a", function() {
        event.preventDefault();

        $(".overlay").fadeOut();

        var data = {
            message: "<a href=" + event.target.href + " target=\"_blank\">" + event.target.text + "</>",
            user: user
        };

        websocket.send(JSON.stringify(data));

        return false;
    });

    $(".overlay input").keyup(function() {
        var searchTerm = $(".overlay input").val();
        var container = $(".overlay .content");

        var url = "http://gdata.youtube.com/feeds/api/videos/?v=2&alt=jsonc&callback=?";

        /* Hide movie rentals */
        url += "&paid-content=false";

        /* Order by view count */
        url += "&oderby=viewCount";

        /* Max videos per request */
        url += "&max-results=15";

        $.getJSON(url + "&q=" + searchTerm, function(json) {
            var html = "";

            if (json.data.items) {
                json.data.items.forEach(function(item) {
                    html += '<div class="video"><img src="http://i.ytimg.com/vi/' + item.id + '/default.jpg">';
                    html += '<h2><a href="http://youtu.be/' + item.id + '">' + item.title + '</a></h2>';
                    html += '<p>' + item.description + '</p><div style="clear: both"></div></div>';
                });

                container.html(html);
            }
        });
    });
});
