/**
 * Created by Rakesh on 4/12/2015.
 */
var WebSocketServer = require('websocket').server;
var http = require('http');
var AWS = require('aws-sdk');
var clients = {};
var global_counter = 0;
var updatedText;
var s3 = new AWS.S3();

var server = http.createServer(function (request, response) {
    console.log((new Date()) + ' Received request for ' + request.url);
    response.writeHead(404);
    response.end();
}).listen(8090, '192.168.1.244');
/*
 server.listen(8090, function () {

 console.log((new Date()) + ' Server is listening on port 8090');
 });
 */
String.prototype.insertAt = function (index, character) {
    return this.substr(0, index) + character + this.substr(index);
};

String.prototype.replaceAt = function (index, character) {
    return this.substr(0, index - 1) + this.substr(index);
};

wsServer = new WebSocketServer({
    httpServer: server,
    autoAcceptConnections: false
});

function originIsAllowed(origin) {
    return true;
}

wsServer.on('request', function (request) {
    if (!originIsAllowed(request.origin)) {
        // Make sure we only accept requests from an allowed origin
        request.reject();
        console.log((new Date()) + ' Connection from origin ' + request.origin + ' rejected.');
        return;
    }

    var connection = request.accept('echo-protocol', request.origin);
    console.log((new Date()) + ' Connection accepted.' + connection.remoteAddress);
    // we need to know client index to remove them on 'close' event
    var id = global_counter++;
    clients[id] = connection;
    connection.on('message', function (message) {
        /*    if (message.type !== 'utf8') {
         if (message.type === 'binary') {
         console.log('Received Binary Message of ' + message.binaryData.length + ' bytes');
         connection.sendBytes(message.binaryData);
         }
         } else {*/
        console.log('Received Message: ' + message.utf8Data);
        // Loop through all clients
        for (var i in clients) {
            // Send a message to the client with the message
            clients[i].sendUTF(message.utf8Data);
            console.log(clients[i].remoteAddress);
        }

        var temp = message.utf8Data.split(',');
        var text = temp[0];
        var pos = temp[1];
        var filePath = temp[2];
        var fileName = filePath.substring(filePath.lastIndexOf('/') + 1);
        var contents = temp[3];
        if (text != "") {
            updatedText = contents.insertAt(pos, text);
        } else {
            updatedText = contents.replaceAt(pos, text);
        }
        s3.putObject({
            Bucket: "dfes1",
            Key: fileName,
            Body: updatedText,
            ACL: 'public-read'
        }, function (resp) {
            console.log('Successfully uploaded package.');
        });

    });
    connection.on('close', function (reasonCode, description) {
        console.log((new Date()) + ' Peer ' + connection.remoteAddress + ' disconnected.');
        // remove user from the list of connected clients
        delete clients[id];
    });
});

