<?php


namespace App\Http\Controllers\API\ClientGroups;


use App\Http\Controllers\Controller;
use App\Models\ClientGroups\ClientGroups;
use Illuminate\Http\Request;
use \Exception;

class FreeClientGroupListController extends Controller
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
    public function GetClientGroupsFreely(Request $request)
    {
        try {
            $groups = ClientGroups::where("CompanyID", "=", $this->AUTH_USER->CompanyID)
                ->where("IsDeleted", "=", 0)
                ->orderBy('id', 'DESC');


            if ($groups->exists()) {
                $groups = $groups->get();
                foreach ($groups as $group) {
                    $group->title = $group->Name;
                }
            } else {
                $groups = [];
            }

            return response()->json([
                'success' => true,
                'message' => 'Free client groups Got Successfully!',
                'client_groups' => $groups
            ]);
        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to get the client groups ! ' . $exception->getMessage(),
            ]);

        }
    }
}
