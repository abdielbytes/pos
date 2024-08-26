<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Userbranch;
use App\Models\Adjustment;
use App\Models\AdjustmentDetail;
use App\Models\ProductVariant;
use App\Models\product_branch;
use App\Models\Role;
use App\Models\branch;
use App\utils\helpers;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdjustmentController extends BaseController
{

    //------------ Show All Adjustement  -----------\\

    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Adjustment::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers();
        // Filter fields With Params to retrieve
        $columns = array(0 => 'Ref', 1 => 'branch_id', 2 => 'date');
        $param = array(0 => 'like', 1 => '=', 2 => '=');
        $data = array();

        // Check If User Has Permission View  All Records
        $Adjustments = Adjustment::with('branch')
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            });

        //Multiple Filter
        $Filtred = $helpers->filter($Adjustments, $columns, $param, $request)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('branch', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });
        $totalRows = $Filtred->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $Adjustments = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($Adjustments as $Adjustment) {
            $item['id'] = $Adjustment->id;
            $item['date'] = $Adjustment->date;
            $item['Ref'] = $Adjustment->Ref;
            $item['branch_name'] = $Adjustment['branch']->name;
            $item['items'] = $Adjustment->items;
            $data[] = $item;
        }

         //get branches assigned to user
         $user_auth = auth()->user();
         if($user_auth->is_all_branches){
            $branches = branch::where('deleted_at', '=', null)->get(['id', 'name']);
         }else{
            $branches_id = Userbranch::where('user_id', $user_auth->id)->pluck('branch_id')->toArray();
            $branches = branch::where('deleted_at', '=', null)->whereIn('id', $branches_id)->get(['id', 'name']);
         }
        return response()->json([
            'adjustments' => $data,
            'totalRows' => $totalRows,
            'branches' => $branches,
        ]);

    }

    //------------ Store New Adjustement -----------\\

    public function store(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'create', Adjustment::class);

        request()->validate([
            'branch_id' => 'required',
        ]);

        \DB::transaction(function () use ($request) {
            $order = new Adjustment;
            $order->date = $request->date;
            $order->Ref = $this->getNumberOrder();
            $order->branch_id = $request->branch_id;
            $order->notes = $request->notes;
            $order->items = sizeof($request['details']);
            $order->user_id = Auth::user()->id;
            $order->save();

            $data = $request['details'];
            $i = 0;
            foreach ($data as $key => $value) {
                $orderDetails[] = [
                    'adjustment_id' => $order->id,
                    'quantity' => $value['quantity'],
                    'product_id' => $value['product_id'],
                    'product_variant_id' => $value['product_variant_id'],
                    'type' => $value['type'],
                ];

                if ($value['type'] == "add") {
                    if ($value['product_variant_id'] !== null) {
                        $product_branch = product_branch::where('deleted_at', '=', null)
                            ->where('branch_id', $order->branch_id)
                            ->where('product_id', $value['product_id'])
                            ->where('product_variant_id', $value['product_variant_id'])
                            ->first();

                        if ($product_branch) {
                            $product_branch->qte += $value['quantity'];
                            $product_branch->save();
                        }

                    } else {
                        $product_branch = product_branch::where('deleted_at', '=', null)
                            ->where('branch_id', $order->branch_id)
                            ->where('product_id', $value['product_id'])
                            ->first();

                        if ($product_branch) {
                            $product_branch->qte += $value['quantity'];
                            $product_branch->save();
                        }
                    }
                } else {

                    if ($value['product_variant_id'] !== null) {
                        $product_branch = product_branch::where('deleted_at', '=', null)
                            ->where('branch_id', $order->branch_id)
                            ->where('product_id', $value['product_id'])
                            ->where('product_variant_id', $value['product_variant_id'])
                            ->first();

                        if ($product_branch) {
                            $product_branch->qte -= $value['quantity'];
                            $product_branch->save();
                        }

                    } else {
                        $product_branch = product_branch::where('deleted_at', '=', null)
                            ->where('branch_id', $order->branch_id)
                            ->where('product_id', $value['product_id'])
                            ->first();

                        if ($product_branch) {
                            $product_branch->qte -= $value['quantity'];
                            $product_branch->save();
                        }
                    }
                }
            }
            AdjustmentDetail::insert($orderDetails);
        }, 10);

        return response()->json(['success' => true]);
    }

    //------------ function show -----------\\

    public function show($id){
    //

    }

    //--------------- Update Adjustment ----------------------\\

    public function update(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', Adjustment::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $current_adjustment = Adjustment::findOrFail($id);

        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === Adjustment->id
            $this->authorizeForUser($request->user('api'), 'check_record', $current_adjustment);
        }

        request()->validate([
            'branch_id' => 'required',
        ]);

        \DB::transaction(function () use ($request, $id, $current_adjustment) {

            $old_adjustment_details = AdjustmentDetail::where('adjustment_id', $id)->get();
            $new_adjustment_details = $request['details'];
            $length = sizeof($new_adjustment_details);

            // Get Ids for new Details
            $new_products_id = [];
            foreach ($new_adjustment_details as $new_detail) {
                $new_products_id[] = $new_detail['id'];
            }

            $old_products_id = [];
            // Init Data with old Parametre
            foreach ($old_adjustment_details as $key => $value) {
                $old_products_id[] = $value->id;
                if ($value['type'] == "add") {

                    if ($value['product_variant_id'] !== null) {
                        $product_branch = product_branch::where('deleted_at', '=', null)
                            ->where('branch_id', $current_adjustment->branch_id)
                            ->where('product_id', $value['product_id'])
                            ->where('product_variant_id', $value['product_variant_id'])
                            ->first();

                        if ($product_branch) {
                            $product_branch->qte -= $value['quantity'];
                            $product_branch->save();
                        }

                    } else {
                        $product_branch = product_branch::where('deleted_at', '=', null)
                            ->where('branch_id', $current_adjustment->branch_id)
                            ->where('product_id', $value['product_id'])
                            ->first();

                        if ($product_branch) {
                            $product_branch->qte -= $value['quantity'];
                            $product_branch->save();
                        }
                    }
                } else {
                    if ($value['product_variant_id'] !== null) {
                        $product_branch = product_branch::where('deleted_at', '=', null)
                            ->where('branch_id', $current_adjustment->branch_id)
                            ->where('product_id', $value['product_id'])
                            ->where('product_variant_id', $value['product_variant_id'])
                            ->first();

                        if ($product_branch) {
                            $product_branch->qte += $value['quantity'];
                            $product_branch->save();
                        }

                    } else {
                        $product_branch = product_branch::where('deleted_at', '=', null)
                            ->where('branch_id', $current_adjustment->branch_id)
                            ->where('product_id', $value['product_id'])
                            ->first();

                        if ($product_branch) {
                            $product_branch->qte += $value['quantity'];
                            $product_branch->save();
                        }
                    }
                }

                // Delete Detail
                if (!in_array($old_products_id[$key], $new_products_id)) {
                    $AdjustmentDetail = AdjustmentDetail::findOrFail($value->id);
                    $AdjustmentDetail->delete();
                }

            }

            // Update Data with New request
            foreach ($new_adjustment_details as $key => $product_detail) {
                if ($product_detail['type'] == "add") {

                    if ($product_detail['product_variant_id'] !== null) {
                        $product_branch = product_branch::where('deleted_at', '=', null)
                            ->where('branch_id', $request->branch_id)
                            ->where('product_id', $product_detail['product_id'])
                            ->where('product_variant_id', $product_detail['product_variant_id'])
                            ->first();

                        if ($product_branch) {
                            $product_branch->qte += $product_detail['quantity'];
                            $product_branch->save();
                        }

                    } else {
                        $product_branch = product_branch::where('deleted_at', '=', null)
                            ->where('branch_id', $request->branch_id)
                            ->where('product_id', $product_detail['product_id'])
                            ->first();

                        if ($product_branch) {
                            $product_branch->qte += $product_detail['quantity'];
                            $product_branch->save();
                        }
                    }
                } else {
                    if ($value['product_variant_id'] !== null) {
                        $product_branch = product_branch::where('deleted_at', '=', null)
                            ->where('branch_id', $request->branch_id)
                            ->where('product_id', $product_detail['product_id'])
                            ->where('product_variant_id', $product_detail['product_variant_id'])
                            ->first();

                        if ($product_branch) {
                            $product_branch->qte -= $product_detail['quantity'];
                            $product_branch->save();
                        }

                    } else {
                        $product_branch = product_branch::where('deleted_at', '=', null)
                            ->where('branch_id', $request->branch_id)
                            ->where('product_id', $product_detail['product_id'])
                            ->first();

                        if ($product_branch) {
                            $product_branch->qte -= $product_detail['quantity'];
                            $product_branch->save();
                        }
                    }
                }

                $orderDetails['adjustment_id'] = $id;
                $orderDetails['quantity'] = $product_detail['quantity'];
                $orderDetails['product_id'] = $product_detail['product_id'];
                $orderDetails['product_variant_id'] = $product_detail['product_variant_id'];
                $orderDetails['type'] = $product_detail['type'];

                if (!in_array($product_detail['id'], $old_products_id)) {
                    AdjustmentDetail::Create($orderDetails);
                } else {
                    AdjustmentDetail::where('id', $product_detail['id'])->update($orderDetails);
                }

            }

            $current_adjustment->update([
                'branch_id' => $request['branch_id'],
                'notes' => $request['notes'],
                'date' => $request['date'],
                'items' => $length,
            ]);

        }, 10);

        return response()->json(['success' => true]);
    }

    //------------ Delete Adjustement -----------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Adjustment::class);

        \DB::transaction(function () use ($id, $request) {
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $current_adjustment = Adjustment::findOrFail($id);
            $old_adjustment_details = AdjustmentDetail::where('adjustment_id', $id)->get();

            // Check If User Has Permission view All Records
            if (!$view_records) {
                // Check If User->id === current_adjustment->id
                $this->authorizeForUser($request->user('api'), 'check_record', $current_adjustment);
            }

            // Init Data with old Parametre
            foreach ($old_adjustment_details as $key => $value) {
                if ($value['type'] == "add") {

                    if ($value['product_variant_id'] !== null) {
                        $product_branch = product_branch::where('deleted_at', '=', null)
                            ->where('branch_id', $current_adjustment->branch_id)
                            ->where('product_id', $value['product_id'])
                            ->where('product_variant_id', $value['product_variant_id'])
                            ->first();

                        if ($product_branch) {
                            $product_branch->qte -= $value['quantity'];
                            $product_branch->save();
                        }

                    } else {
                        $product_branch = product_branch::where('deleted_at', '=', null)
                            ->where('branch_id', $current_adjustment->branch_id)
                            ->where('product_id', $value['product_id'])
                            ->first();

                        if ($product_branch) {
                            $product_branch->qte -= $value['quantity'];
                            $product_branch->save();
                        }
                    }
                } else {
                    if ($value['product_variant_id'] !== null) {
                        $product_branch = product_branch::where('deleted_at', '=', null)
                            ->where('branch_id', $current_adjustment->branch_id)
                            ->where('product_id', $value['product_id'])
                            ->where('product_variant_id', $value['product_variant_id'])
                            ->first();

                        if ($product_branch) {
                            $product_branch->qte += $value['quantity'];
                            $product_branch->save();
                        }

                    } else {
                        $product_branch = product_branch::where('deleted_at', '=', null)
                            ->where('branch_id', $current_adjustment->branch_id)
                            ->where('product_id', $value['product_id'])
                            ->first();

                        if ($product_branch) {
                            $product_branch->qte += $value['quantity'];
                            $product_branch->save();
                        }
                    }
                }

            }
            $current_adjustment->details()->delete();

            $current_adjustment->update([
                'deleted_at' => Carbon::now(),
            ]);

        }, 10);

        return response()->json(['success' => true], 200);
    }

    //-------------- Delete by selection  ---------------\\

    public function delete_by_selection(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Adjustment::class);
        \DB::transaction(function () use ($request) {
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $selectedIds = $request->selectedIds;
            foreach ($selectedIds as $adjustment_id) {
                // $Adjustment = Adjustment::findOrFail($adjustment_id);
                $current_adjustment = Adjustment::findOrFail($adjustment_id);
                $old_adjustment_details = AdjustmentDetail::where('adjustment_id', $adjustment_id)->get();

                // Check If User Has Permission view All Records
                if (!$view_records) {
                    // Check If User->id === Adjustment->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $current_adjustment);
                }

                // Init Data with old Parametre
                foreach ($old_adjustment_details as $key => $value) {
                    if ($value['type'] == "add") {

                        if ($value['product_variant_id'] !== null) {
                            $product_branch = product_branch::where('deleted_at', '=', null)
                                ->where('branch_id', $current_adjustment->branch_id)
                                ->where('product_id', $value['product_id'])
                                ->where('product_variant_id', $value['product_variant_id'])
                                ->first();

                            if ($product_branch) {
                                $product_branch->qte -= $value['quantity'];
                                $product_branch->save();
                            }

                        } else {
                            $product_branch = product_branch::where('deleted_at', '=', null)
                                ->where('branch_id', $current_adjustment->branch_id)
                                ->where('product_id', $value['product_id'])
                                ->first();

                            if ($product_branch) {
                                $product_branch->qte -= $value['quantity'];
                                $product_branch->save();
                            }
                        }
                    } else {
                        if ($value['product_variant_id'] !== null) {
                            $product_branch = product_branch::where('deleted_at', '=', null)
                                ->where('branch_id', $current_adjustment->branch_id)
                                ->where('product_id', $value['product_id'])
                                ->where('product_variant_id', $value['product_variant_id'])
                                ->first();

                            if ($product_branch) {
                                $product_branch->qte += $value['quantity'];
                                $product_branch->save();
                            }

                        } else {
                            $product_branch = product_branch::where('deleted_at', '=', null)
                                ->where('branch_id', $current_adjustment->branch_id)
                                ->where('product_id', $value['product_id'])
                                ->first();

                            if ($product_branch) {
                                $product_branch->qte += $value['quantity'];
                                $product_branch->save();
                            }
                        }
                    }

                }
                $current_adjustment->details()->delete();

                $current_adjustment->update([
                    'deleted_at' => Carbon::now(),
                ]);
            }
        }, 10);

        return response()->json(['success' => true], 200);
    }

    //------------ Reference Number of Adjustement  -----------\\

    public function getNumberOrder()
    {

        $last = DB::table('adjustments')->latest('id')->first();

        if ($last) {
            $item = $last->Ref;
            $nwMsg = explode("_", $item);
            $inMsg = $nwMsg[1] + 1;
            $code = $nwMsg[0] . '_' . $inMsg;
        } else {
            $code = 'AD_1111';
        }
        return $code;

    }

    //---------------- Show Form Create Adjustment ---------------\\

    public function create(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Adjustment::class);

          //get branches assigned to user
          $user_auth = auth()->user();
          if($user_auth->is_all_branches){
             $branches = branch::where('deleted_at', '=', null)->get(['id', 'name']);
          }else{
             $branches_id = Userbranch::where('user_id', $user_auth->id)->pluck('branch_id')->toArray();
             $branches = branch::where('deleted_at', '=', null)->whereIn('id', $branches_id)->get(['id', 'name']);
          }

        return response()->json(['branches' => $branches]);
    }

    //-------------Show Form Edit Adjustment-----------\\

    public function edit(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', Adjustment::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $Adjustment_data = Adjustment::with('details.product')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);
        $details = array();
        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === Adjustment->id
            $this->authorizeForUser($request->user('api'), 'check_record', $Adjustment_data);
        }

        if ($Adjustment_data->branch_id) {
            if (branch::where('id', $Adjustment_data->branch_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $adjustment['branch_id'] = $Adjustment_data->branch_id;
            } else {
                $adjustment['branch_id'] = '';
            }
        } else {
            $adjustment['branch_id'] = '';
        }

        $adjustment['notes'] = $Adjustment_data->notes;
        $adjustment['date'] = $Adjustment_data->date;

        $detail_id = 0;
        foreach ($Adjustment_data['details'] as $detail) {

            if ($detail->product_variant_id) {
                $item_product = product_branch::where('product_id', $detail->product_id)
                    ->where('deleted_at', '=', null)
                    ->where('product_variant_id', $detail->product_variant_id)
                    ->where('branch_id', $Adjustment_data->branch_id)
                    ->first();

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $data['id'] = $detail->id;
                $data['detail_id'] = $detail_id += 1;
                $data['quantity'] = $detail->quantity;
                $data['product_id'] = $detail->product_id;
                $data['product_variant_id'] = $detail->product_variant_id;
                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];
                $data['current'] = $item_product ? $item_product->qte : 0;
                $data['type'] = $detail->type;
                $data['unit'] = $detail['product']['unit']->ShortName;
                $item_product ?$data['del'] = 0:$data['del'] = 1;


            } else {
                $item_product = product_branch::where('product_id', $detail->product_id)
                    ->where('deleted_at', '=', null)
                    ->where('branch_id', $Adjustment_data->branch_id)
                    ->where('product_variant_id', '=', null)
                    ->first();
                    
                    $data['id'] = $detail->id;
                    $data['detail_id'] = $detail_id += 1;
                    $data['quantity'] = $detail->quantity;
                    $data['product_id'] = $detail->product_id;
                    $data['product_variant_id'] = null;
                    $data['code'] = $detail['product']['code'];
                    $data['name'] = $detail['product']['name'];
                    $data['current'] = $item_product ? $item_product->qte : 0;
                    $data['type'] = $detail->type;
                    $data['unit'] = $detail['product']['unit']->ShortName;
                    $item_product ?$data['del'] = 0:$data['del'] = 1;
            }

            $details[] = $data;
        }

       
        //get branches assigned to user
         $user_auth = auth()->user();
         if($user_auth->is_all_branches){
            $branches = branch::where('deleted_at', '=', null)->get(['id', 'name']);
         }else{
            $branches_id = Userbranch::where('user_id', $user_auth->id)->pluck('branch_id')->toArray();
            $branches = branch::where('deleted_at', '=', null)->whereIn('id', $branches_id)->get(['id', 'name']);
         }

        return response()->json([
            'details' => $details,
            'adjustment' => $adjustment,
            'branches' => $branches,
        ]);
    }

    //---------------- Get Details Adjustment-----------------\\

    public function Adjustment_detail(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'view', Adjustment::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $Adjustment_data = Adjustment::with('details.product.unit')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);
        $details = array();
        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === Adjustment->id
            $this->authorizeForUser($request->user('api'), 'check_record', $Adjustment_data);
        }

        $Adjustment['Ref'] = $Adjustment_data->Ref;
        $Adjustment['date'] = $Adjustment_data->date;
        $Adjustment['note'] = $Adjustment_data->notes;
        $Adjustment['branch'] = $Adjustment_data['branch']->name;

        foreach ($Adjustment_data['details'] as $detail) {
            if ($detail->product_variant_id) {

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)
                    ->first();

                $data['quantity'] = $detail->quantity;
                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];
                $data['unit'] = $detail['product']['unit']->ShortName;
                $data['type'] = $detail->type;

            } else {

                $data['quantity'] = $detail->quantity;
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
                $data['type'] = $detail->type;
                $data['unit'] = $detail['product']['unit']->ShortName;
            }

            $details[] = $data;
        }

        return response()->json([
            'details' => $details,
            'adjustment' => $Adjustment,
        ]);
    }


}
