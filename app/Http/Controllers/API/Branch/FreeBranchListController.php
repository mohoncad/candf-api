<?php


namespace App\Http\Controllers\API\Branch;


use App\Http\Controllers\Controller;
use App\Models\Branch\Branch;
use Illuminate\Http\Request;
use \Exception;

class FreeBranchListController extends Controller
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
    public function GetBranchesFreely(Request $request)
    {
        try {
            $BranchList = Branch::where("CompanyID", "=", $this->AUTH_USER->CompanyID)->where("IsDeleted", "=", 0)->orderBy('id', 'DESC');


            if ($BranchList->exists()) {
                $BranchList = $BranchList->get();
                foreach ($BranchList as $Branch) {
                    $Branch->title = $Branch->Name;
                }
            } else {
                $BranchList = [];
            }

            return response()->json([
                'success' => true,
                'message' => 'Free branch List Got Successfully!',
                'branch_list' => $BranchList
            ]);
        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to get the branch list! ' . $exception->getMessage(),
            ]);

        }
    }
}
