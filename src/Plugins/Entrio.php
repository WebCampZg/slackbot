<?php

namespace WebCamp\SlackBot\Plugins;

use WebCamp\SlackBot\AbstractPlugin;
use WebCamp\SlackBot\SlackBot;

class Entrio extends AbstractPlugin
{
    protected $data;
    protected $interval = 60*10;
    protected $ticketsURL = 'https://www.entrio.hr/api/webcamp/get_ticket_count';
    protected $visitorsURL = 'https://www.entrio.hr/api/webcamp/get_visitors';

    public function __construct(SlackBot $bot, $apiKey)
    {
        parent::__construct($bot);

        $loop = $bot->getLoop();
        $client = $bot->getHttpClient();

        // Form URLs for fetching data
        $visitorsURL = $this->visitorsURL . "?key=$apiKey";
        $ticketsURL = $this->ticketsURL . "?key=$apiKey";

        // Fetch entrio data periodically
        $this->log->addInfo("Entrio plugin enabled; enabling polling every $this->interval seconds.");
        $this->setupPolling($client, $visitorsURL, "visitors", $this->interval);
        $this->setupPolling($client, $ticketsURL, "tickets", $this->interval);
    }

    public function getTrigger()
    {
        return "entrio";
    }

    public function getCommands()
    {
        return [
            "tickets" => [$this, "tickets"]
        ];
    }

    public function handle($channel, array $tokens)
    {
        if (empty($tokens)) {
            $this->showHelp($channel);
            return;
        }

        $command = array_shift($tokens);

        switch($command) {
            case "tickets":
                $this->tickets($channel);
                break;
            default:
                $this->showHelp($channel);
                break;
        }
    }

    protected function tickets($channel, $tokens)
    {
        if (!isset($this->data['tickets'])) {
            $this->bot->send($channel, "No data. Wait a little bit.");
            return;
        }

        $ticketData = $this->data['tickets'];
        foreach ($ticketData as $record) {
            if ($record->count > 0) {
                $message = sprintf("%s: %d", $record->category_name, $record->count);
                $this->bot->send($channel, $message);
            }
        }
    }

    protected function showHelp($channel)
    {
        $this->bot->send($channel, "Available commands for Entrio: tickets");
    }

    protected function setupPolling($client, $url, $name, $interval)
    {
        $loop = $this->bot->getLoop();

        // Fetch once on boot
        $this->fetchData($client, $url, $name);

        // And again every $interval seconds
        $loop->addPeriodicTimer($interval, function() use ($client, $url, $name) {
            $this->fetchData($client, $url, $name);
        });
    }

    protected function fetchData($client, $url, $name)
    {
        $this->log->addDebug("Fetching $name data from Entrio...");

        $request = $client->request('GET', $url);
        $request->on('response', function ($response) use ($name) {
            $buffer = '';

            $response->on('data', function ($data) use (&$buffer) {
                $buffer .= $data;
            });

            $response->on('end', function () use (&$buffer, $name) {
                $this->handleData($buffer, $name);
                $this->log->addDebug("Entrio $name data loaded.");
            });
        });
        $request->end();
    }

    protected function handleData($data, $name)
    {
        // Remove the callback(); wrapper
        $json = substr($data, 9, -2);

        $decoded = json_decode($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMsg = json_last_error_msg();
            $this->log->addError("Failed parsing Entrio JSON: $errorMsg");
            return;
        }

        // Check for changes
        if (isset($this->data[$name])) {
            // TODO
        }

        // Save data
        $this->data[$name] = $decoded;
    }
}
