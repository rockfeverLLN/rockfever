<?php
/**.
 * User: Nicolas van de Walle
 * Date: 22-Mar-19
 * Time: 12:34
 */

namespace App\Events\Actions\Ajax;

use App\Events\Table\DancerTable;
use Framework\Actions\AjaxAction;
use Framework\Database\NoRecordException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

class AddImprovisedDancerAction extends AjaxAction
{

    /**
     * @var DancerTable
     */
    protected $table;

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
        $params = $this->getParams($request);
        $params['date'] = $params['status'] > 0 ? date("Y-m-d H:i:s") : null;
        try {
            $this->table->findDuplicates($params['firstname'], $params['lastname'], $params['event_id']);
            return "ALREADY";
        } catch (NoRecordException $e) {
            if ($this->table->insert($params)) {
                return "OK";
            } else {
                return "NOK";
            }
        }
    }

    /**
     * @param Request $request
     * @param null $item
     * @return array
     */
    protected function getParams(Request $request, $item = null) : array
    {
        return array_filter($request->getParsedBody(), function ($key) {
            return in_array($key, ['firstname', 'lastname', 'event_id', 'status']);
        }, ARRAY_FILTER_USE_KEY);
    }
}
