<?php

namespace WebCamp\SlackBot;

interface PluginInterface
{
    public function getTrigger();

    public function handle($channel, array $tokens);
}
