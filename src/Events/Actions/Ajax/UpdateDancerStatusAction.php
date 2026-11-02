<?php
/**.
 * User: Nicolas van de Walle
 * Date: 14-Mar-19
 * Time: 20:41
 */

namespace App\Events\Actions\Ajax;

use App\Events\Table\DancerTable;
use Framework\Actions\AjaxAction;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

class UpdateDancerStatusAction extends AjaxAction
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
            $params = $this->getParams($request);
            $id = $params['id'];
            unset($params['id']);
            $params['date'] = $params['status'] > 0 ? date("Y-m-d H:i:s") : null;

            if ($this->table->update($id, $params)) {
                return "OK";
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
        // guests.js shows an error for anything but "OK"
        return new Response(500, [], 'NOK');
    }

    /**
     * @param Request $request
     * @param null $item
     * @return array
     */
    protected function getParams(Request $request, $item = null) : array
    {
        return array_filter($request->getParsedBody(), function ($key) {
            return in_array($key, ['id', 'status']);
        }, ARRAY_FILTER_USE_KEY);
    }
}
