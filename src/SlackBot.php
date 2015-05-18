<?php

namespace WebCamp\SlackBot;

use Ratchet\Client\WebSocket;
use React\EventLoop\LoopInterface;
use Monolog\Logger;
use React\HttpClient\Client;

class SlackBot
{
    private $httpClient;
    private $log;
    private $messageID = 0;
    private $plugins = [];
    private $slackData;
    private $userID;
    private $webSocket;

    public function __construct(
        WebSocket $webSocket,
        Client $httpClient,
        LoopInterface $loop,
        Logger $log,
        array $slackData
    )
    {
        $this->httpClient = $httpClient;
        $this->log = $log;
        $this->loop = $loop;
        $this->slackData = $slackData;
        $this->webSocket = $webSocket;

        // Save the bot's user ID
        $this->userID = $slackData['self']['id'];

        $webSocket->on('message', function($json) {
            $this->onMessage($json);
        });

        $webSocket->on('error', function($error) {
            $this->log->addError("Error: $error");
        });

        $webSocket->on('close', function($error) {
            $this->log->addInfo("Websocket closed");
        });
    }

    public function addPlugin(PluginInterface $plugin)
    {
        $command = $plugin->getTrigger();
        $this->plugins[$command] = $plugin;
    }

    protected function onMessage($json)
    {
        $this->log->addDebug(">>>>>  $json");
        $message = json_decode($json);

        // Ignore replies
        if (isset($message->reply_to)) {
            return;
        }

        // Ignore edits
        if (!isset($message->type) || !isset($message->text)) {
            return;
        }

        if ($message->type == "message") {
            $uid = $this->userID;
            $mention = "<@$uid>:";

            // Handle direct messages and mentions in channels
            if ($message->channel[0] == "D") {
                $command = trim($message->text);
                $this->onCommand($message->channel, $command);
            } elseif (preg_match("/^$mention/", $message->text)) {
                $command = trim(substr($message->text, strlen($mention)));
                $this->onCommand($message->channel, $command);
            }
        }
    }

    protected function onCommand($channel, $command)
    {
        // Normalize whitespace and split into tokens
        $command = preg_replace("/\\s+/", " ", $command);
        $tokens = explode(" ", $command);

        if (empty($tokens)) {
            $this->send($channel, "ke?");
            return;
        }

        // The first token is the trigger word
        $trigger = array_shift($tokens);

        if (isset($this->plugins[$trigger])) {
            $plugin = $this->plugins[$trigger];
            $plugin->handle($channel, $tokens);
        } else {
            $this->send($channel, "ke?");
        }
    }

    public function send($channel, $text)
    {
        $this->messageID += 1;

        $message = json_encode([
            'id' => $this->messageID,
            'type' => 'message',
            'channel' => $channel,
            'text' => $text
        ]);

        $this->log->debug("<<<<<  $message");

        $this->webSocket->send($message);
    }

    public function getLoop()
    {
        return $this->loop;
    }

    public function getLogger()
    {
        return $this->log;
    }

    public function getHttpClient()
    {
        return $this->httpClient;
    }
}
