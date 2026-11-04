<?php

namespace App\Events\Actions\Ajax;

use App\Events\Table\DancerTable;
use Framework\Actions\AjaxAction;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Takes a dancer off an event's list for good: someone who cancels, or a name added by mistake. They can sign up again
 */
class RemoveDancerAction extends AjaxAction
{

    public function __construct(DancerTable $table)
    {
        parent::__construct($table);
    }

    /**
     * @param Request $request
     * @return ResponseInterface|string
     */
    public function edit(Request $request)
    {
        try {
            if ($this->table->delete((int) ($request->getParsedBody()['id'] ?? 0))) {
                return "OK";
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
        // guests.js shows an error for anything but "OK"
        return new Response(500, [], 'NOK');
    }
}
