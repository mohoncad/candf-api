<?php


namespace App\Http\Controllers\API\Port;


use App\Http\Controllers\Controller;
use App\Models\Bank\Bank;
use App\Models\Port\Port;
use Illuminate\Http\Request;
use \Exception;

class FreePortListController extends Controller
{
    /**
     * @var \Illuminate\Contracts\Auth\Authenticatable|null
     */
    private $AUTH_USER;

    public function __construct()
    {
        $this->AUTH_USER = auth()->user();
    }

    /**
     * Freely Get all the user roles
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function GetPortsFreely(Request $request)
    {
        try {
            $ports = Port::where("CompanyID", "=", $this->AUTH_USER->CompanyID)
                ->where("IsDeleted", "=", 0)
                ->orderBy('id', 'DESC');


            if ($ports->exists()) {
                $ports = $ports->get();
                foreach ($ports as $port) {
                    $port->title = $port->Name;
                }
            } else {
                $ports = [];
            }

            return response()->json([
                'success' => true,
                'message' => 'Free Ports Got Successfully!',
                'ports' => $ports
            ]);
        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to get the ports! ' . $exception->getMessage(),
            ]);

        }
    }
}
