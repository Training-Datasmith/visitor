<?php

declare (strict_types=1);
namespace Shetabit\Visitor\Drivers;

use Illuminate\Http\Request;
use Shetabit\Visitor\Agent;
use Shetabit\Visitor\Contracts\User_Agent_Parser;
class Jenssegers_Agent implements User_Agent_Parser
{
    /**
     * Request container.
     */
    protected Request $request;
    /**
     * Agent parser.
     */
    protected Agent $parser;
    /**
     * Parser constructor.
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->parser = $this->init_parser();
    }
    /**
     * Retrieve device's name.
     */
    public function device(): string
    {
        return $this->parser->device();
    }
    /**
     * Retrieve platform's name.
     */
    public function platform(): string
    {
        return $this->parser->platform();
    }
    /**
     * Retrieve browser's name.
     */
    public function browser(): string
    {
        return $this->parser->browser();
    }
    /**
     * Retrieve languages.
     */
    public function languages(): array
    {
        return $this->parser->languages();
    }
    /**
     * Initialize userAgent parser.
     */
    protected function init_parser(): Agent
    {
        $parser = new Agent();
        $user_agent = $this->request->user_agent() ?? '';
        $parser->set_user_agent($user_agent);
        $parser->set_http_headers((array) $this->request->headers);
        return $parser;
    }
}