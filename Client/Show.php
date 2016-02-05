<?php
/**
 * Created by PhpStorm.
 * User: Rakesh
 * Date: 4/9/2015
 * Time: 8:19 PM
 */
session_start();
require 'aws/aws-autoloader.php';
use Aws\DynamoDb\DynamoDbClient;

//connect to dynamodb
$client = DynamoDbClient::factory(array(
    'credentials' => array('aws_access_key_id' => 'XXXXXXXXX',
        'aws_secret_access_key' => 'XXXXXXXXX'),
//    'profile' => 'Default',
    'region' => 'us-west-2'  // replace with your desired region

));

$response1 = $client->scan(array(
    'TableName' => 'Files'
));
?>
<!DOCTYPE html>
<html>
<head><title>Online Editor</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
    <script type="text/javascript">
        $(document).ready(function () {

            //TO APPEND THE NEWLY ADDED CHARACTER AT THE CURSOR POSITION

            String.prototype.insertAt = function (index, string) {
                return this.substr(0, index) + string + this.substr(index);
            };

            String.prototype.replaceAt = function (index, character) {
                return this.substr(0, index - 1) + this.substr(index);

            };

            //TO RETRIEVE THE CURSOR POSITION
            (function ($, undefined) {
                $.fn.getCursorPosition = function () {
                    var el = $(this).get(0);
                    var pos = 0;
                    if ('selectionStart' in el) {
                        pos = el.selectionStart;
                    } else if ('selection' in document) {
                        el.focus();
                        var Sel = document.selection.createRange();
                        var SelLength = document.selection.createRange().text.length;
                        Sel.moveStart('character', -el.value.length);
                        pos = Sel.text.length - SelLength;
                    }
                    return pos;
                }
            })(jQuery);

            var socket;
            var addedText;
            try {
                socket = new WebSocket('ws://192.168.1.244:8090', 'echo-protocol');
                console.log('Socket Status: ' + socket.readyState);

                socket.onopen = function () {
                    console.log('Socket Status: ' + socket.readyState + ' (open)');
                    $("#status").text("Socket Status-Open");
                };

                socket.onmessage = function (msg) {
                    console.log(msg);

                    //EXTRACT CONTENTS FROM THE MESSAGE RECEIVED
                    var data = msg.data; //CONSISTS OF CHAR,POS,FILEPATH
                    var data1 = data.split(','); //SPLIT THE MESSAGE
                    var text = data1[0]; //CHAR
                    var pos = data1[1]; //POS
                    var tempName = data1[2].substring(data1[2].lastIndexOf('/') + 1); //FILENAME
                    var textContent = data1[3];

                    //GET FILENAME FROM BROWSER TO CHECK IF THE FILE BEING EDITED IS THE SAME AS WE ARE APPENDING
                    var url = window.location.toString();
                    var tempUrl = url.split('=');
                    var filePath = tempUrl[1];
                    var fileName = filePath.substring(filePath.lastIndexOf('/') + 1);

                    //CHECK IF ITS THE CORRECT FILENAME AND SEE IF THE NEW TEXT IS THE SAME AS THE ONE CLIENT IS SENDING
                    if (text == "" && fileName == tempName) {
                        var val = textContent.replaceAt(pos, text);
                        $("#textArea").val(val);
                    }
                    else if (text != addedText && fileName == tempName) {
                        var newTextAreaValue = textContent.insertAt(pos, text);
                        $("#textArea").val(newTextAreaValue);
                    }
                    console.log('Received: ' + msg.data);
                };

                socket.onclose = function () {
                    console.log('Socket Status: ' + socket.readyState + ' (Closed)');
                    $("#status").text("Socket Status-Closed");

                };
                //LISTEN TO KEYBOARD EVENTS
                $("#textArea").keydown(function (event) {
                    //GET THE URL TO NOTIFY SERVER WHICH FILE WE ARE EDITING
                    var url = window.location.toString();
                    var tempUrl = url.split('=');
                    var filePath = tempUrl[1];
                    var cur = $("#textArea").getCursorPosition();
                    var temp = $("#textArea").val();

                    //CHECK FOR BACKSPACE
                    if (event.keyCode == 8) {
                        var val = "" + "," + cur + "," + filePath + "," + temp;
                        socket.send(val);
                    } else if (event.keyCode == 13) {
                        var val1 = "\n" + "," + cur + "," + filePath + "," + temp;
                        socket.send(val1);
                    } else if (event.keyCode == 20 ||event.keyCode == 16 || event.keyCode == 17 || event.keyCode == 18) {
                        console.log("special character received" + event.key);
                    } else if (event.keyCode == 39) {
                        console.log("arrow keys- Arrow left");
                    } else if (event.keyCode == 37) {
                        console.log("arrow keys- Arrow right");
                    } else {
                        addedText = event.key;
                        var val2 = addedText + "," + cur + "," + filePath + "," + temp;
                        socket.send(val2);
                    }
                });
            } catch (exception) {
                console.log('<p>Error' + exception);
            }
        });
    </script>
</head>
<body>
<div id="status"></div>
<textarea id="textArea" rows="35" cols="100"><?php
    if (isset($_GET['url'])) {
        $url = $_GET['url'];
        echo file_get_contents($url);
    }
    ?></textarea>
<table>
    <tr>
    </tr>
    <?php foreach ($response1['Items'] as $key => $value) { ?>
        <tr>
            <td><a href="Show.php?url=<?php echo $value['url']['S'] ?>"><?php echo basename($value['url']['S']) ?></a>
            </td>
        </tr>
    <?php } ?>
</table>

</body>
</html>



