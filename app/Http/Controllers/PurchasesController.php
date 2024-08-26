<?php

namespace App\Http\Controllers;

use Twilio\Rest\Client as Client_Twilio;
use GuzzleHttp\Client as Client_guzzle;
use App\Models\SMSMessage;
use Infobip\Api\SendSmsApi;
use Infobip\Configuration;
use Infobip\Model\SmsAdvancedTextualRequest;
use Infobip\Model\SmsDestination;
use Infobip\Model\SmsTextualMessage;
use Illuminate\Support\Str;
use App\Mail\CustomEmail;
use App\Models\EmailMessage;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\PurchaseMail;
use App\Models\PaymentPurchase;
use App\Models\Product;
use App\Models\Unit;
use App\Models\ProductVariant;
use App\Models\product_branch;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnDetails;
use App\Models\Provider;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Role;
use App\Models\Setting;
use App\Models\branch;
use App\Models\User;
use App\Models\Userbranch;
use App\utils\helpers;
use Carbon\Carbon;
use \Nwidart\Modules\Facades\Module;
use App\Models\sms_gateway;
use DB;
use PDF;
use ArPHP\I18N\Arabic;

class PurchasesController extends BaseController
{

    //------------- Show ALL Purchases ----------\\

    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Purchase::class);
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
        $param = array(
            0 => 'like',
            1 => 'like',
            2 => '=',
            3 => 'like',
            4 => '=',
            5 => '=',
        );
        $columns = array(
            0 => 'Ref',
            1 => 'statut',
            2 => 'provider_id',
            3 => 'payment_statut',
            4 => 'branch_id',
            5 => 'date',
        );
        $data = array();
        $total = 0;

        // Check If User Has Permission View  All Records
        $Purchases = Purchase::with('facture', 'provider', 'branch')
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            });

        //Multiple Filter
        $Filtred = $helpers->filter($Purchases, $columns, $param, $request)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('GrandTotal', $request->search)
                        ->orWhere('payment_statut', 'like', "$request->search")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('provider', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
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
        $Purchases = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($Purchases as $Purchase) {

            $item['id'] = $Purchase->id;
            $item['date'] = $Purchase->date;
            $item['Ref'] = $Purchase->Ref;
            $item['branch_name'] = $Purchase['branch']->name;
            $item['discount'] = $Purchase->discount;
            $item['shipping'] = $Purchase->shipping;
            $item['statut'] = $Purchase->statut;
            $item['provider_id'] = $Purchase['provider']->id;
            $item['provider_name'] = $Purchase['provider']->name;
            $item['provider_email'] = $Purchase['provider']->email;
            $item['provider_tele'] = $Purchase['provider']->phone;
            $item['provider_code'] = $Purchase['provider']->code;
            $item['provider_adr'] = $Purchase['provider']->adresse;
            $item['GrandTotal'] = number_format($Purchase->GrandTotal, 2, '.', '');
            $item['paid_amount'] = number_format($Purchase->paid_amount, 2, '.', '');
            $item['due'] = number_format($item['GrandTotal'] - $item['paid_amount'], 2, '.', '');
            $item['payment_status'] = $Purchase->payment_statut;

            if (PurchaseReturn::where('purchase_id', $Purchase['id'])->where('deleted_at', '=', null)->exists()) {
                $PurchaseReturn = PurchaseReturn::where('purchase_id', $Purchase['id'])->where('deleted_at', '=', null)->first();
                $item['purchasereturn_id'] = $PurchaseReturn->id;
                $item['purchase_has_return'] = 'yes';
            }else{
                $item['purchase_has_return'] = 'no';
            }

            $data[] = $item;
        }

        $suppliers = provider::where('deleted_at', '=', null)->get(['id', 'name']);

         //get branches assigned to user
         $user_auth = auth()->user();
         if($user_auth->is_all_branches){
             $branches = branch::where('deleted_at', '=', null)->get(['id', 'name']);
         }else{
             $branches_id = Userbranch::where('user_id', $user_auth->id)->pluck('branch_id')->toArray();
             $branches = branch::where('deleted_at', '=', null)->whereIn('id', $branches_id)->get(['id', 'name']);
         } 

        return response()->json([
            'totalRows' => $totalRows,
            'purchases' => $data,
            'suppliers' => $suppliers,
            'branches' => $branches,
        ]);
    }

    //------ Store new Purchase -------------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Purchase::class);

        request()->validate([
            'supplier_id' => 'required',
            'branch_id' => 'required',
        ]);

        \DB::transaction(function () use ($request) {
            $order = new Purchase;

            $order->date = $request->date;
            $order->Ref = $this->getNumberOrder();
            $order->provider_id = $request->supplier_id;
            $order->GrandTotal = $request->GrandTotal;
            $order->branch_id = $request->branch_id;
            $order->tax_rate = $request->tax_rate;
            $order->TaxNet = $request->TaxNet;
            $order->discount = $request->discount;
            $order->shipping = $request->shipping;
            $order->statut = $request->statut;
            $order->payment_statut = 'unpaid';
            $order->notes = $request->notes;
            $order->user_id = Auth::user()->id;

            $order->save();

            $data = $request['details'];
            foreach ($data as $key => $value) {
                $unit = Unit::where('id', $value['purchase_unit_id'])->first();
                $orderDetails[] = [
                    'purchase_id' => $order->id,
                    'quantity' => $value['quantity'],
                    'cost' => $value['Unit_cost'],
                    'purchase_unit_id' =>  $value['purchase_unit_id'],
                    'TaxNet' => $value['tax_percent'],
                    'tax_method' => $value['tax_method'],
                    'discount' => $value['discount'],
                    'discount_method' => $value['discount_Method'],
                    'product_id' => $value['product_id'],
                    'product_variant_id' => $value['product_variant_id'],
                    'total' => $value['subtotal'],
                    'imei_number' => $value['imei_number'],
                ];

                if ($order->statut == "received") {
                    if ($value['product_variant_id'] !== null) {
                        $product_branch = product_branch::where('deleted_at', '=', null)
                            ->where('branch_id', $order->branch_id)
                            ->where('product_id', $value['product_id'])
                            ->where('product_variant_id', $value['product_variant_id'])
                            ->first();

                        if ($unit && $product_branch) {
                            if ($unit->operator == '/') {
                                $product_branch->qte += $value['quantity'] / $unit->operator_value;
                            } else {
                                $product_branch->qte += $value['quantity'] * $unit->operator_value;
                            }
                            $product_branch->save();
                        }

                    } else {
                        $product_branch = product_branch::where('deleted_at', '=', null)
                            ->where('branch_id', $order->branch_id)
                            ->where('product_id', $value['product_id'])
                            ->first();

                        if ($unit && $product_branch) {
                            if ($unit->operator == '/') {
                                $product_branch->qte += $value['quantity'] / $unit->operator_value;
                            } else {
                                $product_branch->qte += $value['quantity'] * $unit->operator_value;
                            }
                            $product_branch->save();
                        }
                    }
                }
            }
            PurchaseDetail::insert($orderDetails);
        }, 10);

        return response()->json(['success' => true, 'message' => 'Purchase Created !!']);
    }

    //--------- Update Purchase  -------------\\

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Purchase::class);

        request()->validate([
            'branch_id' => 'required',
            'supplier_id' => 'required',
        ]);

        \DB::transaction(function () use ($request, $id) {
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $current_Purchase = Purchase::findOrFail($id);

            if (PurchaseReturn::where('purchase_id', $id)->where('deleted_at', '=', null)->exists()) {
                return response()->json(['success' => false , 'Return exist for the Transaction' => false], 403);
            }else{

                // Check If User Has Permission view All Records
                if (!$view_records) {
                    // Check If User->id === Purchase->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $current_Purchase);
                }

                $old_purchase_details = PurchaseDetail::where('purchase_id', $id)->get();
                $new_purchase_details = $request['details'];
                $length = sizeof($new_purchase_details);

                // Get Ids for new Details
                $new_products_id = [];
                foreach ($new_purchase_details as $new_detail) {
                    $new_products_id[] = $new_detail['id'];
                }

                // Init Data with old Parametre
                $old_products_id = [];
                foreach ($old_purchase_details as $key => $value) {
                    $old_products_id[] = $value->id;
                
                    //check if detail has purchase_unit_id Or Null
                    if($value['purchase_unit_id'] !== null){
                        $unit = Unit::where('id', $value['purchase_unit_id'])->first();
                    }else{
                        $product_unit_purchase_id = Product::with('unitPurchase')
                        ->where('id', $value['product_id'])
                        ->first();
                        $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
                    }

                    if($value['purchase_unit_id'] !== null){
                        if ($current_Purchase->statut == "received") {

                            if ($value['product_variant_id'] !== null) {
                                $product_branch = product_branch::where('deleted_at', '=', null)
                                    ->where('branch_id', $current_Purchase->branch_id)
                                    ->where('product_id', $value['product_id'])
                                    ->where('product_variant_id', $value['product_variant_id'])
                                    ->first();

                                if ($unit && $product_branch) {
                                    if ($unit->operator == '/') {
                                        $product_branch->qte -= $value['quantity'] / $unit->operator_value;
                                    } else {
                                        $product_branch->qte -= $value['quantity'] * $unit->operator_value;
                                    }

                                    $product_branch->save();
                                }

                            } else {
                                $product_branch = product_branch::where('deleted_at', '=', null)
                                    ->where('branch_id', $current_Purchase->branch_id)
                                    ->where('product_id', $value['product_id'])
                                    ->first();

                                if ($unit && $product_branch) {
                                    if ($unit->operator == '/') {
                                        $product_branch->qte -= $value['quantity'] / $unit->operator_value;
                                    } else {
                                        $product_branch->qte -= $value['quantity'] * $unit->operator_value;
                                    }

                                    $product_branch->save();
                                }
                            }
                        }

                        // Delete Detail
                        if (!in_array($old_products_id[$key], $new_products_id)) {
                            $PurchaseDetail = PurchaseDetail::findOrFail($value->id);
                            $PurchaseDetail->delete();
                        }
                    }

                }

                // Update Data with New request
                foreach ($new_purchase_details as $key => $prod_detail) {
                    
                    if($prod_detail['no_unit'] !== 0){
                        $unit_prod = Unit::where('id', $prod_detail['purchase_unit_id'])->first();

                        if ($request['statut'] == "received") {

                            if ($prod_detail['product_variant_id'] !== null) {
                                $product_branch = product_branch::where('deleted_at', '=', null)
                                    ->where('branch_id', $request->branch_id)
                                    ->where('product_id', $prod_detail['product_id'])
                                    ->where('product_variant_id', $prod_detail['product_variant_id'])
                                    ->first();

                                if ($unit_prod && $product_branch) {
                                    if ($unit_prod->operator == '/') {
                                        $product_branch->qte += $prod_detail['quantity'] / $unit_prod->operator_value;
                                    } else {
                                        $product_branch->qte += $prod_detail['quantity'] * $unit_prod->operator_value;
                                    }

                                    $product_branch->save();
                                }

                            } else {
                                $product_branch = product_branch::where('deleted_at', '=', null)
                                    ->where('branch_id', $request->branch_id)
                                    ->where('product_id', $prod_detail['product_id'])
                                    ->first();

                                if ($unit_prod && $product_branch) {
                                    if ($unit_prod->operator == '/') {
                                        $product_branch->qte += $prod_detail['quantity'] / $unit_prod->operator_value;
                                    } else {
                                        $product_branch->qte += $prod_detail['quantity'] * $unit_prod->operator_value;
                                    }

                                    $product_branch->save();
                                }
                            }

                        }

                        $orderDetails['purchase_id'] = $id;
                        $orderDetails['cost'] = $prod_detail['Unit_cost'];
                        $orderDetails['purchase_unit_id'] = $prod_detail['purchase_unit_id'];
                        $orderDetails['TaxNet'] = $prod_detail['tax_percent'];
                        $orderDetails['tax_method'] = $prod_detail['tax_method'];
                        $orderDetails['discount'] = $prod_detail['discount'];
                        $orderDetails['discount_method'] = $prod_detail['discount_Method'];
                        $orderDetails['quantity'] = $prod_detail['quantity'];
                        $orderDetails['product_id'] = $prod_detail['product_id'];
                        $orderDetails['product_variant_id'] = $prod_detail['product_variant_id'];
                        $orderDetails['total'] = $prod_detail['subtotal'];
                        $orderDetails['imei_number'] = $prod_detail['imei_number'];

                        if (!in_array($prod_detail['id'], $old_products_id)) {
                            PurchaseDetail::Create($orderDetails);
                        } else {
                            PurchaseDetail::where('id', $prod_detail['id'])->update($orderDetails);
                        }
                    }
                }

                $due = $request['GrandTotal'] - $current_Purchase->paid_amount;
                if ($due === 0.0 || $due < 0.0) {
                    $payment_statut = 'paid';
                } else if ($due != $request['GrandTotal']) {
                    $payment_statut = 'partial';
                } else if ($due == $request['GrandTotal']) {
                    $payment_statut = 'unpaid';
                }

                $current_Purchase->update([
                    'date' => $request['date'],
                    'provider_id' => $request['supplier_id'],
                    'branch_id' => $request['branch_id'],
                    'notes' => $request['notes'],
                    'tax_rate' => $request['tax_rate'],
                    'TaxNet' => $request['TaxNet'],
                    'discount' => $request['discount'],
                    'shipping' => $request['shipping'],
                    'statut' => $request['statut'],
                    'GrandTotal' => $request['GrandTotal'],
                    'payment_statut' => $payment_statut,
                ]);
            }

        }, 10);

        return response()->json(['success' => true, 'message' => 'Purchase Updated !!']);

    }

    //------ Delete Purchase -------------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Purchase::class);

        \DB::transaction(function () use ($id, $request) {
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $current_Purchase = Purchase::findOrFail($id);
            $old_purchase_details = PurchaseDetail::where('purchase_id', $id)->get();

            if (PurchaseReturn::where('purchase_id', $id)->where('deleted_at', '=', null)->exists()) {
                return response()->json(['success' => false , 'Return exist for the Transaction' => false], 403);
            }else{

                // Check If User Has Permission view All Records
                if (!$view_records) {
                    // Check If User->id === current_Purchase->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $current_Purchase);
                }

                foreach ($old_purchase_details as $key => $value) {
                
                    //check if detail has purchase_unit_id Or Null
                    if($value['purchase_unit_id'] !== null){
                        $unit = Unit::where('id', $value['purchase_unit_id'])->first();
                    }else{
                        $product_unit_purchase_id = Product::with('unitPurchase')
                        ->where('id', $value['product_id'])
                        ->first();
                        $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
                    }

                    if ($current_Purchase->statut == "received") {

                        if ($value['product_variant_id'] !== null) {
                            $product_branch = product_branch::where('deleted_at', '=', null)
                                ->where('branch_id', $current_Purchase->branch_id)
                                ->where('product_id', $value['product_id'])
                                ->where('product_variant_id', $value['product_variant_id'])
                                ->first();

                            if ($unit && $product_branch) {
                                if ($unit->operator == '/') {
                                    $product_branch->qte -= $value['quantity'] / $unit->operator_value;
                                } else {
                                    $product_branch->qte -= $value['quantity'] * $unit->operator_value;
                                }

                                $product_branch->save();
                            }

                        } else {
                            $product_branch = product_branch::where('deleted_at', '=', null)
                                ->where('branch_id', $current_Purchase->branch_id)
                                ->where('product_id', $value['product_id'])
                                ->first();

                            if ($unit && $product_branch) {
                                if ($unit->operator == '/') {
                                    $product_branch->qte -= $value['quantity'] / $unit->operator_value;
                                } else {
                                    $product_branch->qte -= $value['quantity'] * $unit->operator_value;
                                }

                                $product_branch->save();
                            }
                        }
                    }
                }

                $current_Purchase->details()->delete();
                $current_Purchase->update([
                    'deleted_at' => Carbon::now(),
                ]);

                PaymentPurchase::where('purchase_id', $id)->update([
                    'deleted_at' => Carbon::now(),
                ]);
            }

        }, 10);

        return response()->json(['success' => true, 'message' => 'Purchase Deleted !!']);
    }

    //-------------- Delete by selection  ---------------\\

    public function delete_by_selection(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'delete', Purchase::class);

        \DB::transaction(function () use ($request) {
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $selectedIds = $request->selectedIds;

            foreach ($selectedIds as $purchase_id) {

                if (PurchaseReturn::where('purchase_id', $purchase_id)->where('deleted_at', '=', null)->exists()) {
                    return response()->json(['success' => false , 'Return exist for the Transaction' => false], 403);
                }else{

                    $current_Purchase = Purchase::findOrFail($purchase_id);
                    $old_purchase_details = PurchaseDetail::where('purchase_id', $purchase_id)->get();
                    // Check If User Has Permission view All Records
                    if (!$view_records) {
                        // Check If User->id === current_Purchase->id
                        $this->authorizeForUser($request->user('api'), 'check_record', $current_Purchase);
                    }
                    foreach ($old_purchase_details as $key => $value) {
                
                        //check if detail has purchase_unit_id Or Null
                        if($value['purchase_unit_id'] !== null){
                            $unit = Unit::where('id', $value['purchase_unit_id'])->first();
                        }else{
                            $product_unit_purchase_id = Product::with('unitPurchase')
                            ->where('id', $value['product_id'])
                            ->first();
                            $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
                        }
        
                        if ($current_Purchase->statut == "received") {
        
                            if ($value['product_variant_id'] !== null) {
                                $product_branch = product_branch::where('deleted_at', '=', null)
                                    ->where('branch_id', $current_Purchase->branch_id)
                                    ->where('product_id', $value['product_id'])
                                    ->where('product_variant_id', $value['product_variant_id'])
                                    ->first();
        
                                if ($unit && $product_branch) {
                                    if ($unit->operator == '/') {
                                        $product_branch->qte -= $value['quantity'] / $unit->operator_value;
                                    } else {
                                        $product_branch->qte -= $value['quantity'] * $unit->operator_value;
                                    }
        
                                    $product_branch->save();
                                }
        
                            } else {
                                $product_branch = product_branch::where('deleted_at', '=', null)
                                    ->where('branch_id', $current_Purchase->branch_id)
                                    ->where('product_id', $value['product_id'])
                                    ->first();
        
                                if ($unit && $product_branch) {
                                    if ($unit->operator == '/') {
                                        $product_branch->qte -= $value['quantity'] / $unit->operator_value;
                                    } else {
                                        $product_branch->qte -= $value['quantity'] * $unit->operator_value;
                                    }
        
                                    $product_branch->save();
                                }
                            }
                        }
                    }
        
                    $current_Purchase->details()->delete();
                    $current_Purchase->update([
                        'deleted_at' => Carbon::now(),
                    ]);

                    PaymentPurchase::where('purchase_id', $purchase_id)->update([
                        'deleted_at' => Carbon::now(),
                    ]);
                }
            }

        }, 10);
        return response()->json(['success' => true, 'message' => 'Purchase Deleted !!']);

    }


    //---------------- Get Details Purchase -----------------\\

    public function show(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'view', Purchase::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $purchase = Purchase::with('details.product.unitPurchase')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        
        $details = array();

        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === purchase->id
            $this->authorizeForUser($request->user('api'), 'check_record', $purchase);
        }

        $purchase_data['Ref'] = $purchase->Ref;
        $purchase_data['date'] = $purchase->date;
        $purchase_data['statut'] = $purchase->statut;
        $purchase_data['note'] = $purchase->notes;
        $purchase_data['discount'] = $purchase->discount;
        $purchase_data['shipping'] = $purchase->shipping;
        $purchase_data['tax_rate'] = $purchase->tax_rate;
        $purchase_data['TaxNet'] = $purchase->TaxNet;
        $purchase_data['supplier_name'] = $purchase['provider']->name;
        $purchase_data['supplier_email'] = $purchase['provider']->email;
        $purchase_data['supplier_phone'] = $purchase['provider']->phone;
        $purchase_data['supplier_adr'] = $purchase['provider']->adresse;
        $purchase_data['supplier_tax'] = $purchase['provider']->tax_number;
        $purchase_data['branch'] = $purchase['branch']->name;
        $purchase_data['GrandTotal'] = number_format($purchase->GrandTotal, 2, '.', '');
        $purchase_data['paid_amount'] = number_format($purchase->paid_amount, 2, '.', '');
        $purchase_data['due'] = number_format($purchase_data['GrandTotal'] - $purchase_data['paid_amount'], 2, '.', '');
        $purchase_data['payment_status'] = $purchase->payment_statut;

        if (PurchaseReturn::where('purchase_id', $id)->where('deleted_at', '=', null)->exists()) {
            $PurchaseReturn = PurchaseReturn::where('purchase_id', $id)->where('deleted_at', '=', null)->first();
            $purchase_data['purchasereturn_id'] = $PurchaseReturn->id;
            $purchase_data['purchase_has_return'] = 'yes';
        }else{
            $purchase_data['purchase_has_return'] = 'no';
        }

        foreach ($purchase['details'] as $detail) {

             //-------check if detail has purchase_unit_id Or Null
             if($detail->purchase_unit_id !== null){
                $unit = Unit::where('id', $detail->purchase_unit_id)->first();
            }else{
                $product_unit_purchase_id = Product::with('unitPurchase')
                ->where('id', $detail->product_id)
                ->first();
                $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
            }

            if ($detail->product_variant_id) {

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];
           
            } else {
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
            }
            
            $data['quantity'] = $detail->quantity;
            $data['total'] = $detail->total;
            $data['cost'] = $detail->cost;
            $data['unit_purchase'] = $unit->ShortName;

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = $detail->discount;
            } else {
                $data['DiscountNet'] = $detail->cost * $detail->discount / 100;
            }

            $tax_cost = $detail->TaxNet * (($detail->cost - $data['DiscountNet']) / 100);
            $data['Unit_cost'] = $detail->cost;
            $data['discount'] = $detail->discount;

            if ($detail->tax_method == '1') {

                $data['Net_cost'] = $detail->cost - $data['DiscountNet'];
                $data['taxe'] = $tax_cost;
            } else {
                $data['Net_cost'] = ($detail->cost - $data['DiscountNet']) / (($detail->TaxNet / 100) + 1);
                $data['taxe'] = $detail->cost - $data['Net_cost'] - $data['DiscountNet'];
            }

            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            $details[] = $data;
        }

        $company = Setting::where('deleted_at', '=', null)->first();

        return response()->json([
            'details' => $details,
            'purchase' => $purchase_data,
            'company' => $company,
        ]);

    }

    //--------------- Get Payments of Purchase ----------------\\

    public function Get_Payments(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'view', PaymentPurchase::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $purchase = Purchase::findOrFail($id);

        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === purchase->id
            $this->authorizeForUser($request->user('api'), 'check_record', $purchase);
        }

        $payments = PaymentPurchase::with('purchase')
            ->where('purchase_id', $id)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })->orderBy('id', 'DESC')->get();

        $due = $purchase->GrandTotal - $purchase->paid_amount;

        return response()->json(['payments' => $payments, 'due' => $due]);
    }

    //--------------- Reference Number of Purchase ----------------\\

    public function getNumberOrder()
    {

        $last = DB::table('purchases')->latest('id')->first();

        if ($last) {
            $item = $last->Ref;
            $nwMsg = explode("_", $item);
            $inMsg = $nwMsg[1] + 1;
            $code = $nwMsg[0] . '_' . $inMsg;
        } else {
            $code = 'PR_1111';
        }
        return $code;

    }

    //-------------- purchase PDF -----------\\

    public function Purchase_pdf(Request $request, $id)
    {
        $details = array();
        $helpers = new helpers();
        $Purchase_data = Purchase::with('details.product.unitPurchase')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $purchase['supplier_name'] = $Purchase_data['provider']->name;
        $purchase['supplier_phone'] = $Purchase_data['provider']->phone;
        $purchase['supplier_adr'] = $Purchase_data['provider']->adresse;
        $purchase['supplier_email'] = $Purchase_data['provider']->email;
        $purchase['supplier_tax'] = $Purchase_data['provider']->tax_number;
        $purchase['TaxNet'] = number_format($Purchase_data->TaxNet, 2, '.', '');
        $purchase['discount'] = number_format($Purchase_data->discount, 2, '.', '');
        $purchase['shipping'] = number_format($Purchase_data->shipping, 2, '.', '');
        $purchase['statut'] = $Purchase_data->statut;
        $purchase['Ref'] = $Purchase_data->Ref;
        $purchase['date'] = $Purchase_data->date;
        $purchase['GrandTotal'] = number_format($Purchase_data->GrandTotal, 2, '.', '');
        $purchase['paid_amount'] = number_format($Purchase_data->paid_amount, 2, '.', '');
        $purchase['due'] = number_format($purchase['GrandTotal'] - $purchase['paid_amount'], 2, '.', '');
        $purchase['payment_status'] = $Purchase_data->payment_statut;

        $detail_id = 0;
        foreach ($Purchase_data['details'] as $detail) {

            //-------check if detail has purchase_unit_id Or Null
            if($detail->purchase_unit_id !== null){
                $unit = Unit::where('id', $detail->purchase_unit_id)->first();
            }else{
                $product_unit_purchase_id = Product::with('unitPurchase')
                ->where('id', $detail->product_id)
                ->first();
                $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
            }

            if ($detail->product_variant_id) {

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];
            } else {
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
            }

                $data['detail_id'] = $detail_id += 1;
                $data['quantity'] = number_format($detail->quantity, 2, '.', '');
                $data['total'] = number_format($detail->total, 2, '.', '');
                $data['unit_purchase'] = $unit->ShortName;
                $data['cost'] = number_format($detail->cost, 2, '.', '');

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = number_format($detail->discount, 2, '.', '');
            } else {
                $data['DiscountNet'] = number_format($detail->cost * $detail->discount / 100, 2, '.', '');
            }

            $tax_cost = $detail->TaxNet * (($detail->cost - $data['DiscountNet']) / 100);
            $data['Unit_cost'] = number_format($detail->cost, 2, '.', '');
            $data['discount'] = number_format($detail->discount, 2, '.', '');

            if ($detail->tax_method == '1') {

                $data['Net_cost'] = $detail->cost - $data['DiscountNet'];
                $data['taxe'] = number_format($tax_cost, 2, '.', '');
            } else {
                $data['Net_cost'] = ($detail->cost - $data['DiscountNet']) / (($detail->TaxNet / 100) + 1);
                $data['taxe'] = number_format($detail->cost - $data['Net_cost'] - $data['DiscountNet'], 2, '.', '');
            }

            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            $details[] = $data;
        }

        $settings = Setting::where('deleted_at', '=', null)->first();
        $symbol = $helpers->Get_Currency_Code();

        $Html = view('pdf.purchase_pdf', [
            'symbol' => $symbol,
            'setting' => $settings,
            'purchase' => $purchase,
            'details' => $details,
        ])->render();

        $arabic = new Arabic();
        $p = $arabic->arIdentify($Html);

        for ($i = count($p)-1; $i >= 0; $i-=2) {
            $utf8ar = $arabic->utf8Glyphs(substr($Html, $p[$i-1], $p[$i] - $p[$i-1]));
            $Html = substr_replace($Html, $utf8ar, $p[$i-1], $p[$i] - $p[$i-1]);
        }

        $pdf = PDF::loadHTML($Html);
        return $pdf->download('purchase.pdf');

    }

    //---------------- Show Form Create Purchase ---------------\\

    public function create(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'create', Purchase::class);

         //get branches assigned to user
         $user_auth = auth()->user();
         if($user_auth->is_all_branches){
             $branches = branch::where('deleted_at', '=', null)->get(['id', 'name']);
         }else{
             $branches_id = Userbranch::where('user_id', $user_auth->id)->pluck('branch_id')->toArray();
             $branches = branch::where('deleted_at', '=', null)->whereIn('id', $branches_id)->get(['id', 'name']);
         } 

        $suppliers = Provider::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json([
            'branches' => $branches,
            'suppliers' => $suppliers,
        ]);
    }

    //-------------Show Form Edit Purchase-----------\\

    public function edit(Request $request, $id)
    {
        if (PurchaseReturn::where('purchase_id', $id)->where('deleted_at', '=', null)->exists()) {
            return response()->json(['success' => false , 'Return exist for the Transaction' => false], 403);
        }else{

            $this->authorizeForUser($request->user('api'), 'update', Purchase::class);
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $Purchase_data = Purchase::with('details.product.unitPurchase')
                ->where('deleted_at', '=', null)
                ->findOrFail($id);
            $details = array();
            // Check If User Has Permission view All Records
            if (!$view_records) {
                // Check If User->id === Purchase->id
                $this->authorizeForUser($request->user('api'), 'check_record', $Purchase_data);
            }

            if ($Purchase_data->provider_id) {
                if (Provider::where('id', $Purchase_data->provider_id)->where('deleted_at', '=', null)->first()) {
                    $purchase['supplier_id'] = $Purchase_data->provider_id;
                } else {
                    $purchase['supplier_id'] = '';
                }
            } else {
                $purchase['supplier_id'] = '';
            }

            if ($Purchase_data->branch_id) {
                if (branch::where('id', $Purchase_data->branch_id)->where('deleted_at', '=', null)->first()) {
                    $purchase['branch_id'] = $Purchase_data->branch_id;
                } else {
                    $purchase['branch_id'] = '';
                }
            } else {
                $purchase['branch_id'] = '';
            }

            $purchase['date'] = $Purchase_data->date;
            $purchase['tax_rate'] = $Purchase_data->tax_rate;
            $purchase['TaxNet'] = $Purchase_data->TaxNet;
            $purchase['discount'] = $Purchase_data->discount;
            $purchase['shipping'] = $Purchase_data->shipping;
            $purchase['statut'] = $Purchase_data->statut;
            $purchase['notes'] = $Purchase_data->notes;

            $detail_id = 0;
            foreach ($Purchase_data['details'] as $detail) {

                //-------check if detail has purchase_unit_id Or Null
                if($detail->purchase_unit_id !== null){
                    $unit = Unit::where('id', $detail->purchase_unit_id)->first();
                    $data['no_unit'] = 1;
                }else{
                    $product_unit_purchase_id = Product::with('unitPurchase')
                    ->where('id', $detail->product_id)
                    ->first();
                    $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
                    $data['no_unit'] = 0;
                }

                if ($detail->product_variant_id) {
                    $item_product = product_branch::where('product_id', $detail->product_id)
                        ->where('deleted_at', '=', null)
                        ->where('product_variant_id', $detail->product_variant_id)
                        ->where('branch_id', $Purchase_data->branch_id)
                        ->first();

                    $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                        ->where('id', $detail->product_variant_id)->first();

                    $item_product ? $data['del'] = 0 : $data['del'] = 1;

                    $data['code'] = $productsVariants->code;
                    $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];
                    $data['product_variant_id'] = $detail->product_variant_id;                

                    if ($unit && $unit->operator == '/') {
                        $data['stock'] = $item_product ? $item_product->qte * $unit->operator_value : 0;
                    } else if ($unit && $unit->operator == '*') {
                        $data['stock'] = $item_product ? $item_product->qte / $unit->operator_value : 0;
                    } else {
                        $data['stock'] = 0;
                    }

                } else {
                    $item_product = product_branch::where('product_id', $detail->product_id)
                        ->where('deleted_at', '=', null)->where('product_variant_id', '=', null)
                        ->where('branch_id', $Purchase_data->branch_id)->first();

                    $item_product ? $data['del'] = 0 : $data['del'] = 1;
                    $data['product_variant_id'] = null;

                    $data['code'] = $detail['product']['code'];
                    $data['name'] = $detail['product']['name'];
                

                    if ($unit && $unit->operator == '/') {
                        $data['stock'] = $item_product ? $item_product->qte * $unit->operator_value : 0;
                    } else if ($unit && $unit->operator == '*') {
                        $data['stock'] = $item_product ? $item_product->qte / $unit->operator_value : 0;
                    } else {
                        $data['stock'] = 0;
                    }

                }

                $data['id'] = $detail->id;
                $data['detail_id'] = $detail_id += 1;
                $data['quantity'] = $detail->quantity;
                $data['product_id'] = $detail->product_id;
                $data['unitPurchase'] = $unit->ShortName;
                $data['purchase_unit_id'] = $unit->id;
                
                $data['is_imei'] = $detail['product']['is_imei'];
                $data['imei_number'] = $detail->imei_number;

                if ($detail->discount_method == '2') {
                    $data['DiscountNet'] = $detail->discount;
                } else {
                    $data['DiscountNet'] = $detail->cost * $detail->discount / 100;
                }

                $tax_cost = $detail->TaxNet * (($detail->cost - $data['DiscountNet']) / 100);
                $data['Unit_cost'] = $detail->cost;
                $data['tax_percent'] = $detail->TaxNet;
                $data['tax_method'] = $detail->tax_method;
                $data['discount'] = $detail->discount;
                $data['discount_Method'] = $detail->discount_method;

                if ($detail->tax_method == '1') {
                    $data['Net_cost'] = $detail->cost - $data['DiscountNet'];
                    $data['taxe'] = $tax_cost;
                    $data['subtotal'] = ($data['Net_cost'] * $data['quantity']) + ($tax_cost * $data['quantity']);
                } else {
                    $data['Net_cost'] = ($detail->cost - $data['DiscountNet']) / (($detail->TaxNet / 100) + 1);
                    $data['taxe'] = $detail->cost - $data['Net_cost'] - $data['DiscountNet'];
                    $data['subtotal'] = ($data['Net_cost'] * $data['quantity']) + ($tax_cost * $data['quantity']);
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
            
            $suppliers = Provider::where('deleted_at', '=', null)->get(['id', 'name']);

            return response()->json([
                'details' => $details,
                'purchase' => $purchase,
                'suppliers' => $suppliers,
                'branches' => $branches,
            ]);
        }
    }

    //------------------- get_Products_by_purchase -----------------\\

    public function get_Products_by_purchase(Request $request , $id)
    {

        $this->authorizeForUser($request->user('api'), 'create', PurchaseReturn::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $Purchase_data = Purchase::with('details.product.unitPurchase')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $details = array();
        
        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === Purchase->id
            $this->authorizeForUser($request->user('api'), 'check_record', $Purchase_data);
        }

        $Return_detail['supplier_id'] = $Purchase_data->provider_id;
        $Return_detail['branch_id'] = $Purchase_data->branch_id;
        $Return_detail['purchase_id'] = $Purchase_data->id;
        $Return_detail['tax_rate'] = 0;
        $Return_detail['TaxNet'] = 0;
        $Return_detail['discount'] = 0;
        $Return_detail['shipping'] = 0;
        $Return_detail['statut'] = "completed";
        $Return_detail['notes'] = "";

        $detail_id = 0;
        foreach ($Purchase_data['details'] as $detail) {

            //-------check if detail has purchase_unit_id Or Null
            if($detail->purchase_unit_id !== null){
                $unit = Unit::where('id', $detail->purchase_unit_id)->first();
                $data['no_unit'] = 1;
            }else{
                $product_unit_purchase_id = Product::with('unitPurchase')
                ->where('id', $detail->product_id)
                ->first();
                $unit = Unit::where('id', $product_unit_purchase_id['unitPurchase']->id)->first();
                $data['no_unit'] = 0;
            }

            if ($detail->product_variant_id) {
                $item_product = product_branch::where('product_id', $detail->product_id)
                    ->where('deleted_at', '=', null)
                    ->where('product_variant_id', $detail->product_variant_id)
                    ->where('branch_id', $Purchase_data->branch_id)
                    ->first();

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];
                $data['code'] = $productsVariants->code;

                $data['product_variant_id'] = $detail->product_variant_id;                

                if ($unit && $unit->operator == '/') {
                    $data['stock'] = $item_product ? $item_product->qte * $unit->operator_value : 0;
                } else if ($unit && $unit->operator == '*') {
                    $data['stock'] = $item_product ? $item_product->qte / $unit->operator_value : 0;
                } else {
                    $data['stock'] = 0;
                }

            } else {
                $item_product = product_branch::where('product_id', $detail->product_id)
                    ->where('deleted_at', '=', null)->where('product_variant_id', '=', null)
                    ->where('branch_id', $Purchase_data->branch_id)->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['product_variant_id'] = null;
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
                

                if ($unit && $unit->operator == '/') {
                    $data['stock'] = $item_product ? $item_product->qte * $unit->operator_value : 0;
                } else if ($unit && $unit->operator == '*') {
                    $data['stock'] = $item_product ? $item_product->qte / $unit->operator_value : 0;
                } else {
                    $data['stock'] = 0;
                }

            }

            $data['id'] = $detail->id;
            $data['detail_id'] = $detail_id += 1;
            $data['quantity'] = $detail->quantity;
            $data['purchase_quantity'] = $detail->quantity;
            $data['product_id'] = $detail->product_id;
            $data['unitPurchase'] = $unit->ShortName;
            $data['purchase_unit_id'] = $unit->id;
            
            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = $detail->discount;
            } else {
                $data['DiscountNet'] = $detail->cost * $detail->discount / 100;
            }

            $tax_cost = $detail->TaxNet * (($detail->cost - $data['DiscountNet']) / 100);
            $data['Unit_cost'] = $detail->cost;
            $data['tax_percent'] = $detail->TaxNet;
            $data['tax_method'] = $detail->tax_method;
            $data['discount'] = $detail->discount;
            $data['discount_Method'] = $detail->discount_method;

            if ($detail->tax_method == '1') {
                $data['Net_cost'] = $detail->cost - $data['DiscountNet'];
                $data['taxe'] = $tax_cost;
                $data['subtotal'] = ($data['Net_cost'] * $data['quantity']) + ($tax_cost * $data['quantity']);
            } else {
                $data['Net_cost'] = ($detail->cost - $data['DiscountNet']) / (($detail->TaxNet / 100) + 1);
                $data['taxe'] = $detail->cost - $data['Net_cost'] - $data['DiscountNet'];
                $data['subtotal'] = ($data['Net_cost'] * $data['quantity']) + ($tax_cost * $data['quantity']);
            }

            $details[] = $data;
        }

        return response()->json([
            'details' => $details,
            'purchase_return' => $Return_detail,
        ]);

    }


     //------------- Send Email -----------\\

    public function Send_Email(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Purchase::class);

        //purchase
        $purchase = Purchase::with('provider')->where('deleted_at', '=', null)->findOrFail($request->id);

        $helpers = new helpers();
        $currency = $helpers->Get_Currency();

        //settings
        $settings = Setting::where('deleted_at', '=', null)->first();
    
         //the custom msg of sale
         $emailMessage  = EmailMessage::where('name', 'purchase')->first();
 
         if($emailMessage){
             $message_body = $emailMessage->body;
             $message_subject = $emailMessage->subject;
         }else{
             $message_body = '';
             $message_subject = '';
         }
        //Tags
        $random_number = Str::random(10);
        $invoice_url = url('/api/purchase_url/' . $request->id.'?'.$random_number);
        $invoice_number = $purchase->Ref;

        $total_amount = $currency . ' '.number_format($purchase->GrandTotal, 2, '.', ',');
        $paid_amount  = $currency . ' '.number_format($purchase->paid_amount, 2, '.', ',');
        $due_amount   = $currency . ' '.number_format($purchase->GrandTotal - $purchase->paid_amount, 2, '.', ',');

        $contact_name = $purchase['provider']->name;
        $business_name = $settings->CompanyName;

        //receiver email 
        $receiver_email = $purchase['provider']->email;

        //replace the text with tags
        $message_body = str_replace('{contact_name}', $contact_name, $message_body);
        $message_body = str_replace('{business_name}', $business_name, $message_body);
        $message_body = str_replace('{invoice_url}', $invoice_url, $message_body);
        $message_body = str_replace('{invoice_number}', $invoice_number, $message_body);

        $message_body = str_replace('{total_amount}', $total_amount, $message_body);
        $message_body = str_replace('{paid_amount}', $paid_amount, $message_body);
        $message_body = str_replace('{due_amount}', $due_amount, $message_body);

        $email['subject'] = $message_subject;
        $email['body'] = $message_body;
        $email['company_name'] = $business_name;

        $this->Set_config_mail(); 
        $mail = Mail::to($receiver_email)->send(new CustomEmail($email));
        return $mail;
    }

     //-------------------Sms Notifications -----------------\\

     public function Send_SMS(Request $request)
     {
 
        $this->authorizeForUser($request->user('api'), 'view', Purchase::class);

         //purchase
         $purchase = Purchase::with('provider')->where('deleted_at', '=', null)->findOrFail($request->id);
 
         $helpers = new helpers();
         $currency = $helpers->Get_Currency();
 
         //settings
         $settings = Setting::where('deleted_at', '=', null)->first();
     
         $default_sms_gateway = sms_gateway::where('id' , $settings->sms_gateway)
            ->where('deleted_at', '=', null)->first();

         //the custom msg of purchase
         $smsMessage  = SMSMessage::where('name', 'purchase')->first();
 
         if($smsMessage){
             $message_text = $smsMessage->text;
         }else{
             $message_text = '';
         }
 
         //Tags
         $random_number = Str::random(10);
         $invoice_url = url('/api/purchase_pdf/' . $request->id.'?'.$random_number);
         $invoice_number = $purchase->Ref;
 
         $total_amount = $currency . ' '.number_format($purchase->GrandTotal, 2, '.', ',');
         $paid_amount  = $currency . ' '.number_format($purchase->paid_amount, 2, '.', ',');
         $due_amount   = $currency . ' '.number_format($purchase->GrandTotal - $purchase->paid_amount, 2, '.', ',');
 
         $contact_name = $purchase['provider']->name;
         $business_name = $settings->CompanyName;
 
         //receiver Number
         $receiverNumber = $purchase['provider']->phone;
 
         //replace the text with tags
         $message_text = str_replace('{contact_name}', $contact_name, $message_text);
         $message_text = str_replace('{business_name}', $business_name, $message_text);
         $message_text = str_replace('{invoice_url}', $invoice_url, $message_text);
         $message_text = str_replace('{invoice_number}', $invoice_number, $message_text);
 
         $message_text = str_replace('{total_amount}', $total_amount, $message_text);
         $message_text = str_replace('{paid_amount}', $paid_amount, $message_text);
         $message_text = str_replace('{due_amount}', $due_amount, $message_text);
 
         //twilio
         if($default_sms_gateway->title == "twilio"){
             try {
     
                 $account_sid = env("TWILIO_SID");
                 $auth_token = env("TWILIO_TOKEN");
                 $twilio_number = env("TWILIO_FROM");
     
                 $client = new Client_Twilio($account_sid, $auth_token);
                 $client->messages->create($receiverNumber, [
                     'from' => $twilio_number, 
                     'body' => $message_text]);
         
             } catch (Exception $e) {
                 return response()->json(['message' => $e->getMessage()], 500);
             }
         //nexmo
         }elseif($default_sms_gateway->title == "nexmo"){
             try {
 
                 $basic  = new \Nexmo\Client\Credentials\Basic(env("NEXMO_KEY"), env("NEXMO_SECRET"));
                 $client = new \Nexmo\Client($basic);
                 $nexmo_from = env("NEXMO_FROM");
         
                 $message = $client->message()->send([
                     'to' => $receiverNumber,
                     'from' => $nexmo_from,
                     'text' => $message_text
                 ]);
                         
             } catch (Exception $e) {
                 return response()->json(['message' => $e->getMessage()], 500);
             }
 
         //---- infobip
         }elseif($default_sms_gateway->title == "infobip"){
 
             $BASE_URL = env("base_url");
             $API_KEY = env("api_key");
             $SENDER = env("sender_from");
 
             $configuration = (new Configuration())
                 ->setHost($BASE_URL)
                 ->setApiKeyPrefix('Authorization', 'App')
                 ->setApiKey('Authorization', $API_KEY);
             
             $client = new Client_guzzle();
             
             $sendSmsApi = new SendSMSApi($client, $configuration);
             $destination = (new SmsDestination())->setTo($receiverNumber);
             $message = (new SmsTextualMessage())
                 ->setFrom($SENDER)
                 ->setText($message_text)
                 ->setDestinations([$destination]);
                 
             $request = (new SmsAdvancedTextualRequest())->setMessages([$message]);
             
             try {
                 $smsResponse = $sendSmsApi->sendSmsMessage($request);
                 echo ("Response body: " . $smsResponse);
             } catch (Throwable $apiException) {
                 echo("HTTP Code: " . $apiException->getCode() . "\n");
             }
             
         }
 
         return response()->json(['success' => true]);
         
     }




}
