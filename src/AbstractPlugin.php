<?php

namespace WebCamp\SlackBot;

abstract class AbstractPlugin implements PluginInterface
{
    /**
     * @var WebCamp\SlackBot\SlackBot
     */
    protected $bot;

    /**
     * @var Monolog\Logger
     */
    protected $log;

    public function __construct(SlackBot $bot)
    {
        $this->bot = $bot;
        $this->log = $bot->getLogger();
    }
}