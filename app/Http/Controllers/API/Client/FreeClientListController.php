<?php


namespace App\Http\Controllers\API\Client;


use App\Http\Controllers\Controller;
use App\Models\Client\Client;
use Illuminate\Http\Request;
use \Exception;

class FreeClientListController extends Controller
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
    public function GetClientsFreely(Request $request)
    {
        try {
            $ClientList = Client::where("CompanyID", "=", $this->AUTH_USER->CompanyID)
                ->where("IsDeleted", "=", 0)
                ->orderBy('id', 'DESC');


            if ($ClientList->exists()) {
                $ClientList = $ClientList->get();
                foreach ($ClientList as $Client) {
                    $Client->title = 'CT'.$Client->Code.' - '.$Client->Name;
                }
            } else {
                $ClientList = [];
            }

            return response()->json([
                'success' => true,
                'message' => 'Free Client List Got Successfully!',
                'client_list' => $ClientList
            ]);
        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to get the client list! ' . $exception->getMessage(),
            ]);

        }
    }
}
