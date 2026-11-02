<?php
/**.
 * User: Nicolas van de Walle
 * Date: 22-Mar-19
 * Time: 11:37
 */

namespace App\Events\Actions\Ajax;

use App\Events\Table\DancerTable;
use Framework\Actions\AjaxAction;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

class LoadDancersStatsAction extends AjaxAction
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
    public function index(Request $request)
    {
        $id = $request->getAttribute('id');

        $stats = [
            0=>0,   // 0 = Registered
            1=>0,   // 1 = Present
            2=>0,   // 2 = VIP
            3=>0    // 3 = Improvised
        ];
        foreach ($this->table->findStats($id) as $item) {
            $stats[$item->status] = $item->amount;
        }
        return json_encode($stats);
    }
}
