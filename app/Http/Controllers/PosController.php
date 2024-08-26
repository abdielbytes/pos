<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Userbranch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Client;
use App\Models\PaymentSale;
use App\Models\Product;
use App\Models\Setting;
use App\Models\PosSetting;
use App\Models\ProductVariant;
use App\Models\product_branch;
use App\Models\PaymentWithCreditCard;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\SaleDetail;
use App\Models\branch;
use App\utils\helpers;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe;

class PosController extends BaseController
{

    //------------ Create New  POS --------------\\

    public function CreatePOS(Request $request)
    {
        // dd($request->all());
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);

        request()->validate([
            'client_id' => 'required',
            'branch_id' => 'required',
            'payment.amount' => 'required',
        ]);

        $item = \DB::transaction(function () use ($request) {
            $helpers = new helpers();
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $order = new Sale;

            $order->is_pos = 1;
            $order->date = Carbon::now();
            $order->Ref = app('App\Http\Controllers\SalesController')->getNumberOrder();
            $order->client_id = $request->client_id;
            $order->branch_id = $request->branch_id;
            $order->tax_rate = $request->tax_rate;
            $order->TaxNet = $request->TaxNet;
            $order->discount = $request->discount;
            $order->shipping = $request->shipping;
            $order->GrandTotal = $request->GrandTotal;
            $order->notes = $request->notes;
            $order->statut = 'completed';
            $order->payment_statut = 'unpaid';
            $order->user_id = Auth::user()->id;

            $order->save();

            $data = $request['details'];
            foreach ($data as $key => $value) {

                $unit = Unit::where('id', $value['sale_unit_id'])
                    ->first();
                $orderDetails[] = [
                    'date' => Carbon::now(),
                    'sale_id' => $order->id,
                    'sale_unit_id' =>  $value['sale_unit_id'],
                    'quantity' => $value['quantity'],
                    'product_id' => $value['product_id'],
                    'product_variant_id' => $value['product_variant_id'],
                    'total' => $value['subtotal'],
                    'price' => $value['Unit_price'],
                    'TaxNet' => $value['tax_percent'],
                    'tax_method' => $value['tax_method'],
                    'discount' => $value['discount'],
                    'discount_method' => $value['discount_Method'],
                    'imei_number' => $value['imei_number'],
                ];

                if ($value['product_variant_id'] !== null) {
                    $product_branch = product_branch::where('branch_id', $order->branch_id)
                        ->where('product_id', $value['product_id'])->where('product_variant_id', $value['product_variant_id'])
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
                    $product_branch = product_branch::where('branch_id', $order->branch_id)
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

            SaleDetail::insert($orderDetails);

            $sale = Sale::findOrFail($order->id);
            // Check If User Has Permission view All Records
            if (!$view_records) {
                // Check If User->id === sale->id
                $this->authorizeForUser($request->user('api'), 'check_record', $sale);
            }

            try {

                $total_paid = $sale->paid_amount + $request['amount'];
                $due = $sale->GrandTotal - $total_paid;

                if ($due === 0.0 || $due < 0.0) {
                    $payment_statut = 'paid';
                } else if ($due != $sale->GrandTotal) {
                    $payment_statut = 'partial';
                } else if ($due == $sale->GrandTotal) {
                    $payment_statut = 'unpaid';
                }
                              
                if($request['amount'] > 0){
                    if ($request->payment['Reglement'] == 'credit card') {
                        $Client = Client::whereId($request->client_id)->first();
                        Stripe\Stripe::setApiKey(config('app.STRIPE_SECRET'));

                        // Check if the payment record exists
                        $PaymentWithCreditCard = PaymentWithCreditCard::where('customer_id', $request->client_id)->first();
                        if (!$PaymentWithCreditCard) {

                            // Create a new customer and charge the customer with a new credit card
                            $customer = \Stripe\Customer::create([
                                'source' => $request->token,
                                'email'  => $Client->email,
                                'name'   => $Client->name,
                            ]);

                            // Charge the Customer instead of the card:
                            $charge = \Stripe\Charge::create([
                                'amount'   => $request['amount'] * 100,
                                'currency' => 'usd',
                                'customer' => $customer->id,
                            ]);
                            $PaymentCard['customer_stripe_id'] = $customer->id;

                        // Check if the payment record not exists
                        } else {

                             // Retrieve the customer ID and card ID
                            $customer_id = $PaymentWithCreditCard->customer_stripe_id;
                            $card_id = $request->card_id;

                            // Charge the customer with the new credit card or the selected card
                            if ($request->is_new_credit_card || $request->is_new_credit_card == 'true' || $request->is_new_credit_card === 1) {
                                // Retrieve the customer
                                $customer = \Stripe\Customer::retrieve($customer_id);

                                // Create New Source
                                $card = \Stripe\Customer::createSource(
                                    $customer_id,
                                    [
                                      'source' => $request->token,
                                    ]
                                  );

                                $charge = \Stripe\Charge::create([
                                    'amount'   => $request['amount'] * 100,
                                    'currency' => 'usd',
                                    'customer' => $customer_id,
                                    'source'   => $card->id,
                                ]);
                                $PaymentCard['customer_stripe_id'] = $customer_id;

                            } else {
                                $charge = \Stripe\Charge::create([
                                    'amount'   => $request['amount'] * 100,
                                    'currency' => 'usd',
                                    'customer' => $customer_id,
                                    'source'   => $card_id,
                                ]);
                                $PaymentCard['customer_stripe_id'] = $customer_id;
                            }
                        }




                        $PaymentSale            = new PaymentSale();
                        $PaymentSale->sale_id   = $order->id;
                        $PaymentSale->Ref       = app('App\Http\Controllers\PaymentSalesController')->getNumberOrder();
                        $PaymentSale->date      = Carbon::now();
                        $PaymentSale->Reglement = $request->payment['Reglement'];
                        $PaymentSale->montant   = $request['amount'];
                        $PaymentSale->change    = $request['change'];
                        $PaymentSale->notes     = $request->payment['notes'];
                        $PaymentSale->user_id   = Auth::user()->id;
                        $PaymentSale->save();

                        $sale->update([
                            'paid_amount'    => $total_paid,
                            'payment_statut' => $payment_statut,
                        ]);

                        $PaymentCard['customer_id'] = $request->client_id;
                        $PaymentCard['payment_id']  = $PaymentSale->id;
                        $PaymentCard['charge_id']   = $charge->id;
                        PaymentWithCreditCard::create($PaymentCard);

                        // Paying Method Cash
                    } else {

                        PaymentSale::create([
                            'sale_id' => $order->id,
                            'Ref' => app('App\Http\Controllers\PaymentSalesController')->getNumberOrder(),
                            'date' => Carbon::now(),
                            'Reglement' => $request->payment['Reglement'],
                            'montant' => $request['amount'],
                            'change' => $request['change'],
                            'notes' => $request->payment['notes'],
                            'user_id' => Auth::user()->id,
                        ]);

                        $sale->update([
                            'paid_amount' => $total_paid,
                            'payment_statut' => $payment_statut,
                        ]);
                    }

                }
              
            } catch (Exception $e) {
                return response()->json(['message' => $e->getMessage()], 500);
            }

            return $order->id;

        }, 10);

        return response()->json(['success' => true, 'id' => $item]);

    }

    //------------ Get Products--------------\\

    public function GetProductsByParametre(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);
        // How many items do you want to display.
        $perPage = 8;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $data = array();

        $product_branch_data = product_branch::where('branch_id', $request->branch_id)
            ->with('product', 'product.unitSale')
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($request) {
                return $query->whereHas('product', function ($q) use ($request) {
                    $q->where('not_selling', '=', 0);
                })
                ->where(function ($query) use ($request) {
                    if ($request->stock == '1' && $request->product_service == '1') {
                        return $query->where('qte', '>', 0)->orWhere('manage_stock', false);
    
                    }elseif($request->stock == '1' && $request->product_service == '0') {
                        return $query->where('qte', '>', 0)->orWhere('manage_stock', true);
    
                    }else{
                        return $query->where('manage_stock', true);
                    }
                });
            })

        // Filter
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('category_id'), function ($query) use ($request) {
                    return $query->whereHas('product', function ($q) use ($request) {
                        $q->where('category_id', '=', $request->category_id);
                    });
                });
            })
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('brand_id'), function ($query) use ($request) {
                    return $query->whereHas('product', function ($q) use ($request) {
                        $q->where('brand_id', '=', $request->brand_id);
                    });
                });
            });

        $totalRows = $product_branch_data->count();

        $product_branch_data = $product_branch_data
            ->offset($offSet)
            ->limit(8)
            ->get();

        foreach ($product_branch_data as $product_branch) {
            if ($product_branch->product_variant_id) {
                $productsVariants = ProductVariant::where('product_id', $product_branch->product_id)
                    ->where('id', $product_branch->product_variant_id)
                    ->where('deleted_at', null)
                    ->first();

                $item['product_variant_id'] = $product_branch->product_variant_id;
                $item['Variant'] = '['.$productsVariants->name . ']' . $product_branch['product']->name;
                $item['name'] = '['.$productsVariants->name . ']' . $product_branch['product']->name;

                $item['code'] = $productsVariants->code;
                $item['barcode'] = $productsVariants->code;

                $product_price = $product_branch['productVariant']->price;

            } else {
                $item['product_variant_id'] = null;
                $item['Variant'] = null;
                $item['code'] = $product_branch['product']->code;
                $item['name'] = $product_branch['product']->name;
                $item['barcode'] = $product_branch['product']->code;

                $product_price =  $product_branch['product']->price;

            }
            $item['id'] = $product_branch->product_id;
            $firstimage = explode(',', $product_branch['product']->image);
            $item['image'] = $firstimage[0];

            if($product_branch['product']['unitSale']){

                if ($product_branch['product']['unitSale']->operator == '/') {
                    $item['qte_sale'] = $product_branch->qte * $product_branch['product']['unitSale']->operator_value;
                    $price = $product_price / $product_branch['product']['unitSale']->operator_value;

                } else {
                    $item['qte_sale'] = $product_branch->qte / $product_branch['product']['unitSale']->operator_value;
                    $price = $product_price * $product_branch['product']['unitSale']->operator_value;

                }

            }else{
                $item['qte_sale'] = $product_branch['product']->type!='is_service'?$product_branch->qte:'---';
                $price = $product_price;
            }

            $item['unitSale'] = $product_branch['product']['unitSale']?$product_branch['product']['unitSale']->ShortName:'';
            $item['qte'] = $product_branch['product']->type!='is_service'?$product_branch->qte:'---';
            $item['product_type'] = $product_branch['product']->type;
            
            if ($product_branch['product']->TaxNet !== 0.0) {

                //Exclusive
                if ($product_branch['product']->tax_method == '1') {
                    $tax_price = $price * $product_branch['product']->TaxNet / 100;

                    $item['Net_price'] = $price + $tax_price;

                    // Inxclusive
                } else {
                    $item['Net_price'] = $price;
                }
            } else {
                $item['Net_price'] = $price;
            }

            $data[] = $item;
        }

        return response()->json([
            'products' => $data,
            'totalRows' => $totalRows,
        ]);
    }

    //--------------------- Get Element POS ------------------------\\

    public function GetELementPos(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Sales_pos', Sale::class);
        $clients = Client::where('deleted_at', '=', null)->get(['id', 'name']);
        $settings = Setting::where('deleted_at', '=', null)->with('Client')->first();

          //get branches assigned to user
          $user_auth = auth()->user();
          if($user_auth->is_all_branches){
             $branches = branch::where('deleted_at', '=', null)->get(['id', 'name']);

             if ($settings->branch_id) {
                if (branch::where('id', $settings->branch_id)->where('deleted_at', '=', null)->first()) {
                    $defaultbranch = $settings->branch_id;
                } else {
                    $defaultbranch = '';
                }
            } else {
                $defaultbranch = '';
            }

          }else{
             $branches_id = Userbranch::where('user_id', $user_auth->id)->pluck('branch_id')->toArray();
             $branches = branch::where('deleted_at', '=', null)->whereIn('id', $branches_id)->get(['id', 'name']);

             if ($settings->branch_id) {
                if (branch::where('id', $settings->branch_id)->whereIn('id', $branches_id)->where('deleted_at', '=', null)->first()) {
                    $defaultbranch = $settings->branch_id;
                } else {
                    $defaultbranch = '';
                }
            } else {
                $defaultbranch = '';
            }
          }


      
        

        if ($settings->client_id) {
            if (Client::where('id', $settings->client_id)->where('deleted_at', '=', null)->first()) {
                $defaultClient = $settings->client_id;
                $default_client_name = $settings['Client']->name;
            } else {
                $defaultClient = '';
                $default_client_name = '';
            }
        } else {
            $defaultClient = '';
            $default_client_name = '';
        }
        $categories = Category::where('deleted_at', '=', null)->get(['id', 'name']);
        $brands = Brand::where('deleted_at', '=', null)->get();
        $stripe_key = config('app.STRIPE_KEY');

        return response()->json([
            'stripe_key' => $stripe_key,
            'brands' => $brands,
            'defaultbranch' => $defaultbranch,
            'defaultClient' => $defaultClient,
            'default_client_name' => $default_client_name,
            'clients' => $clients,
            'branches' => $branches,
            'categories' => $categories,
        ]);
    }

}
