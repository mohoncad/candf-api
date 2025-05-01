<?php


namespace App\Http\Controllers\API\Port;


use App\Http\Controllers\API\SysCode\SysCodeController;
use App\Http\Controllers\API\UAP\UAP;
use App\Http\Controllers\API\UAP\UAPController;
use App\Http\Controllers\Controller;
use App\Models\Port\Port;
use App\Models\Bill\bill_entry_list;
use App\User;
use \Illuminate\Http\Request;
use \Exception;

class PortController extends Controller
{
    /**
     * @var bool|mixed
     */
    private $MODULE_PERMISSIONS;
    /**
     * @var bool
     */
    private $MODULE_ACCESSIBLE;
    /**
     * @var \Illuminate\Contracts\Auth\Authenticatable|null
     */
    private $AUTH_USER;
    /**
     * @var string
     */
    private $MODULE_CODE;
    /**
     * @var int
     */
    private $DOC_CODE_ZEROES_LENGTH;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->AUTH_USER = auth()->user();
            $this->DOC_CODE_ZEROES_LENGTH = 3;
            $this->MODULE_CODE = UAP::$ModuleCodes['7'];
            $this->MODULE_PERMISSIONS = UAPController::ModulePermissions($this->MODULE_CODE);
            $this->MODULE_ACCESSIBLE = ($this->MODULE_PERMISSIONS === true || (gettype($this->MODULE_PERMISSIONS) === 'object' && $this->MODULE_PERMISSIONS->ModuleAccess));

            if (!$this->MODULE_ACCESSIBLE) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PERMISSION_DENIED',
                    'message' => 'Access Denied! You need permission to perform this action!',
                ], 403);
            }

            return $next($request);
        });
    }


    public function NewDocCode() {
        $SysCodeC = new SysCodeController();
        return $SysCodeC->GenerateNewCode($this->MODULE_CODE, $this->DOC_CODE_ZEROES_LENGTH);
    }

    /**
     * Get all the ports
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function GetAllPorts(Request $request) {

        $search_query = (string) $request->input('search_query');
        $trash_mode = $request->input('trash_mode');
        $trash_mode = $trash_mode === 'true' ? 1 : 0;

        if ($trash_mode === 1) {
            if (!$this->MODULE_PERMISSIONS === true || !$this->MODULE_PERMISSIONS->Trash) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PERMISSION_DENIED',
                    'message' => 'Access Denied! You need permission to perform this action!',
                ], 403);
            }
        }

       try {
           $ports = Port::where('CompanyID', '=', $this->AUTH_USER->CompanyID)
               ->where('IsDeleted', '=', $trash_mode);

           if ($search_query != "") {
               $ports->where("Code", "LIKE", "%" . $search_query . "%")
                   ->orWhere("Name", "LIKE", "%" . $search_query . "%")
                   ->orWhere("PortCode", "LIKE", "%" . $search_query . "%");
           }

           $ports = $ports->orderBy('id', 'DESC')
               ->get();

           foreach ($ports as $port) {
               if ($port->DeletedBy > 0) {
                   $deleted_user = User::where("id", "=", $port->DeletedBy)->first();
                   $port->DeletedBy = $deleted_user->FullName . ($deleted_user->IsSupportUser ? " (Service Provider)" : "");
                   $port->DeletedAt = date('d M, Y - h:ia', strtotime($port->DeletedAt));
               }
           }


           return response()->json([
               'success' => true,
               'message' => 'Ports got successfully!',
               'ports' => $ports,
           ]);
       } catch (Exception $exception) {
           return response()->json([
               'success' => false,
               'error_code' => 'DB_ERROR',
               'message' => 'Failed! ' . $exception->getMessage(),
           ]);
       }
    }

    public function GetSinglePort(Request $request) {
        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->View) {

            $portID = $request->input("id");
            $portCode = $request->input("Code");

            $port = Port::where('CompanyID', '=', $this->AUTH_USER->CompanyID)
                ->where('IsDeleted', '=', 0);

            if($portID != '') {
                $port->where('id', '=', $portID);
            } elseif($portCode != '') {
                $port->where('Code', '=', $portCode);
            } else {
                return response()->json([
                    'success' => false,
                    'error_code' => 'INVALID_ID_OR_CODE',
                    'message' => 'You must provide a port id or code',
                ]);
            }

            if(!$port->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'Port was not found!',
                ]);
            }

            $port = $port->first();

            /**
             * Flags set to boolean for javascript
             */
            $port->IsDeleted = $port->IsDeleted == 1;
            $port->HasEditPermission = $this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit;

            return response()->json([
                'success' => true,
                'message' => 'Port details got successfully!',
                'port' => $port,
            ]);

        }

        return response()->json([
            'success' => false,
            'error_code' => 'PERMISSION_DENIED',
            'message' => 'Access Denied! You need permission to perform this action!',
        ], 403);
    }

    /**
     * Delete a port
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function DeletePort(Request $request)
    {

        $trash_mode = $request->input('trash_mode');
        $trash_mode = $trash_mode === 'true' ? 1 : 0;

        if ($trash_mode === 1) {
            if (!$this->MODULE_PERMISSIONS === true || !$this->MODULE_PERMISSIONS->DeleteForever) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PERMISSION_DENIED',
                    'message' => 'Access Denied! You need permission to perform this action!',
                ], 403);
            }
        }

        if ($trash_mode === 0 && !($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Delete)) {
            return response()->json([
                'success' => false,
                'error_code' => 'PERMISSION_DENIED',
                'message' => 'Access Denied! You need permission to perform this action!',
            ], 403);
        }

        $PortID = $request->input("PortID");
        $CreatedAt = date("Y-m-d H:i:s", time());

        //checking whether this port ID has already been used in any other bill or not
        $existing_port = bill_entry_list::where("port_id", "=" , $PortID);


        $port = Port::where("id", "=", $PortID)
            ->where("CompanyID", "=", $this->AUTH_USER->CompanyID)
            ->where('BranchID', '=', $this->AUTH_USER->BranchID);

        if (!$port->exists()) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested port was not found!',
            ]);
        }

        try {

            if ($trash_mode === 1) {

                if($existing_port->exists())
                {
                    return response()->json([
                        'success' => false,
                        'message' => 'This port has already been used in another bill, unable to delete',
                    ]);
                }
                else
                {
                    $port->delete();
                }

            } else {

                $data = [];
                $data["IsDeleted"] = 1;
                $data["DeletedBy"] = $this->AUTH_USER->id;
                $data["DeletedAt"] = $CreatedAt;
                $port->update($data);

            }

            return response()->json([
                'success' => true,
                'message' => 'Port deleted successfully!',
            ]);

        } catch (Exception $exception) {

            return response()->json([
                'success' => false,
                'error_code' => 'DB_ERROR',
                'message' => 'Failed to delete! ' . $exception->getMessage(),
            ]);

        }


    }


    /**
     * Restore a port
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function RestorePort(Request $request)
    {

        if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Restore) {

            $portID = $request->input("portID");
            $CreatedAt = date("Y-m-d H:i:s", time());

            $port = Port::where("id", "=", $portID)
                ->where("CompanyID", "=", $this->AUTH_USER->CompanyID)
                ->where('BranchID', '=', $this->AUTH_USER->BranchID);

            if (!$port->exists()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                    'message' => 'The requested port was not found!',
                ]);
            }


            try {

                $data = [];
                $data["IsDeleted"] = 0;
                $data["UpdatedBy"] = $this->AUTH_USER->id;
                $data["UpdatedAt"] = $CreatedAt;
                $port->update($data);

                return response()->json([
                    'success' => true,
                    'message' => 'Port restored successfully!',
                ]);

            } catch (Exception $exception) {

                return response()->json([
                    'success' => false,
                    'error_code' => 'DB_ERROR',
                    'message' => 'Failed to restore! ' . $exception->getMessage(),
                ]);

            }

        } else {

            return response()->json([
                'success' => false,
                'error_code' => 'PERMISSION_DENIED',
                'message' => 'Access Denied! You need permission to perform this action!',
            ], 403);

        }
    }

    public function SavePort(Request $request) {
        $id = $request->input("id");
        $Name = $request->input("Name");
        $PortCode = $request->input("PortCode");
        $CreatedAt = date("Y-m-d H:i:s", time());

        $port = Port::where('id', '=', $id)
            ->where('CompanyID', '=', $this->AUTH_USER->CompanyID)
            ->where('BranchID', '=', $this->AUTH_USER->BranchID);

        if($port->exists()) {

            //Update port
            if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Edit) {

                try {
                    $port->update([
                        'Name' => $Name,
                        'PortCode' => $PortCode,
                        'UpdatedBy' => $this->AUTH_USER->id,
                        'UpdatedAt' => $CreatedAt,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Port updated successfully!',
                    ]);
                } catch (Exception $exception) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'DB_ERROR',
                        'message' => 'Failed to update! ' . $exception->getMessage(),
                    ]);
                }

            } else {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PERMISSION_DENIED',
                    'message' => 'Access Denied! You need permission to perform this action!',
                ], 403);
            }

        } else {

            //Insert a new port
            if ($this->MODULE_PERMISSIONS === true || $this->MODULE_PERMISSIONS->Add) {
                $SysCode = new SysCodeController();

                try {
                    Port::Insert([
                        'Code' => $SysCode->GenerateNewCode($this->MODULE_CODE, $this->DOC_CODE_ZEROES_LENGTH),
                        'CompanyID' => $this->AUTH_USER->CompanyID,
                        'BranchID' => $this->AUTH_USER->BranchID,
                        'Name' => $Name,
                        'PortCode' => $PortCode,
                        'CreatedBy' => $this->AUTH_USER->id,
                        'CreatedAt' => $CreatedAt,
                    ]);

                    $SysCode->UpdateIncrement($this->MODULE_CODE);

                    return response()->json([
                        'success' => true,
                        'message' => 'Port created successfully!',
                    ]);
                } catch (Exception $exception) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'DB_ERROR',
                        'message' => 'Failed to create! ' . $exception->getMessage(),
                    ]);
                }

            } else {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PERMISSION_DENIED',
                    'message' => 'Access Denied! You need permission to perform this action!',
                ], 403);
            }

        }
    }
}
