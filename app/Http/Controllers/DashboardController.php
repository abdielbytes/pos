<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Expense;
use App\Models\Unit;
use App\Models\PaymentPurchase;
use App\Models\PaymentPurchaseReturns;
use App\Models\PaymentSale;
use App\Models\PaymentSaleReturns;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\product_branch;
use App\Models\Provider;
use App\Models\Purchase;
use App\Models\Setting;
use App\Models\PurchaseDetail;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnDetails;
use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SaleReturn;
use App\Models\SaleReturnDetails;
use App\Models\User;
use App\Models\Userbranch;
use App\Models\branch;
use App\utils\helpers;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{

    //----------------- dashboard_data -----------------------\\

    public function dashboard_data(Request $request)
    {
        $user_auth = auth()->user();
        if($user_auth->is_all_branches){
            $array_branches_id = branch::where('deleted_at', '=', null)->pluck('id')->toArray();
            $branches = branch::where('deleted_at', '=', null)->get(['id', 'name']);
        }else{
            $array_branches_id = Userbranch::where('user_id', $user_auth->id)->pluck('branch_id')->toArray();
            $branches = branch::where('deleted_at', '=', null)->whereIn('id', $array_branches_id)->get(['id', 'name']);
        }
                    
        if(empty($request->branch_id)){
            $branch_id = 0;
        }else{
            $branch_id = $request->branch_id;
        }



        $dataSales = $this->SalesChart($branch_id, $array_branches_id);
        $datapurchases = $this->PurchasesChart($branch_id, $array_branches_id);
        $Payment_chart = $this->Payment_chart($branch_id, $array_branches_id);
        $TopCustomers = $this->TopCustomers($branch_id, $array_branches_id);
        $Top_Products_Year = $this->Top_Products_Year($branch_id, $array_branches_id);
        $report_dashboard = $this->report_dashboard($branch_id, $array_branches_id);

        return response()->json([
            'branches' => $branches,
            'sales' => $dataSales,
            'purchases' => $datapurchases,
            'payments' => $Payment_chart,
            'customers' => $TopCustomers,
            'product_report' => $Top_Products_Year,
            'report_dashboard' => $report_dashboard,
        ]);

    }
    
    public function UserSalesChart($branch_id, $array_branches_id, $user_id)
    {
    $query = Sale::where('user_id', $user_id);

    if ($branch_id != 0) {
        $query->where('branch_id', $branch_id);
    } else {
        $query->whereIn('branch_id', $array_branches_id);
    }

    // Add any other necessary filtering or aggregation here
    $sales = $query->get();

    // Process $sales to generate the chart data
    $chartData = $this->generateSalesChartData($sales);

    return $chartData;
    }

    
    //----------------- Sales Chart js -----------------------\\

    public function SalesChart($branch_id, $array_branches_id)
    {
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');

        // Build an array of the dates we want to show, oldest first
        $dates = collect();
        foreach (range(-6, 0) as $i) {
            $date = Carbon::now()->addDays($i)->format('Y-m-d');
            $dates->put($date, 0);
        }

        $date_range = \Carbon\Carbon::today()->subDays(6);
        // Get the sales counts
        $sales = Sale::where('date', '>=', $date_range)
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })

            ->where(function ($query) use ($branch_id, $array_branches_id) {
                if ($branch_id !== 0) {
                    return $query->where('branch_id', $branch_id);
                }else{
                    return $query->whereIn('branch_id', $array_branches_id);
                }
            })
            
            ->groupBy(DB::raw("DATE_FORMAT(date,'%Y-%m-%d')"))
            ->orderBy('date', 'asc')
            ->get([
                DB::raw(DB::raw("DATE_FORMAT(date,'%Y-%m-%d') as date")),
                DB::raw('SUM(GrandTotal) AS count'),
            ])
            ->pluck('count', 'date');

        // Merge the two collections;
        $dates = $dates->merge($sales);

        $data = [];
        $days = [];
        foreach ($dates as $key => $value) {
            $data[] = $value;
            $days[] = $key;
        }

        return response()->json(['data' => $data, 'days' => $days]);

    }

    //----------------- Purchases Chart -----------------------\\

    public function PurchasesChart($branch_id, $array_branches_id)
    {

        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');

        // Build an array of the dates we want to show, oldest first
        $dates = collect();
        foreach (range(-6, 0) as $i) {
            $date = Carbon::now()->addDays($i)->format('Y-m-d');
            $dates->put($date, 0);
        }

        $date_range = \Carbon\Carbon::today()->subDays(6);

        // Get the purchases counts
        $purchases = Purchase::where('date', '>=', $date_range)
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where(function ($query) use ($branch_id, $array_branches_id) {
                if ($branch_id !== 0) {
                    return $query->where('branch_id', $branch_id);
                }else{
                    return $query->whereIn('branch_id', $array_branches_id);
                }
            })
            ->groupBy(DB::raw("DATE_FORMAT(date,'%Y-%m-%d')"))
            ->orderBy('date', 'asc')
            ->get([
                DB::raw(DB::raw("DATE_FORMAT(date,'%Y-%m-%d') as date")),
                DB::raw('SUM(GrandTotal) AS count'),
            ])
            ->pluck('count', 'date');

        // Merge the two collections;
        $dates = $dates->merge($purchases);

        $data = [];
        $days = [];
        foreach ($dates as $key => $value) {
            $data[] = $value;
            $days[] = $key;
        }

        return response()->json(['data' => $data, 'days' => $days]);

    }

    //-------------------- Get Top 5 Customers -------------\\

    public function TopCustomers($branch_id, $array_branches_id)
    {
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');

        $data = Sale::whereBetween('date', [
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
        ])->where('sales.deleted_at', '=', null)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('sales.user_id', '=', Auth::user()->id);
                }
            })

            ->where(function ($query) use ($branch_id, $array_branches_id) {
                if ($branch_id !== 0) {
                    return $query->where('sales.branch_id', $branch_id);
                }else{
                    return $query->whereIn('sales.branch_id', $array_branches_id);
                }
            })

            ->join('clients', 'sales.client_id', '=', 'clients.id')
            ->select(DB::raw('clients.name'), DB::raw("count(*) as value"))
            ->groupBy('clients.name')
            ->orderBy('value', 'desc')
            ->take(5)
            ->get();

        return response()->json($data);
    }


    //-------------------- Get Top 5 Products This YEAR -------------\\

    public function Top_Products_Year($branch_id, $array_branches_id)
    {

        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');

        $products = SaleDetail::join('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->join('products', 'sale_details.product_id', '=', 'products.id')
            ->whereBetween('sale_details.date', [
                Carbon::now()->startOfYear(),
                Carbon::now()->endOfYear(),
            ])
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('sales.user_id', '=', Auth::user()->id);
                }
            })

            ->where(function ($query) use ($branch_id, $array_branches_id) {
                if ($branch_id !== 0) {
                    return $query->where('sales.branch_id', $branch_id);
                }else{
                    return $query->whereIn('sales.branch_id', $array_branches_id);
                }
            })
            ->select(
                DB::raw('products.name as name'),
                DB::raw('count(*) as value'),
            )
            ->groupBy('products.name')
            ->orderBy('value', 'desc')
            ->take(5)
            ->get();

        return response()->json($products);
    }
    

    //-------------------- General Report dashboard -------------\\

    public function report_dashboard($branch_id, $array_branches_id)
    {

        $Role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($Role->id)->inRole('record_view');

        // top selling product this month
        $products = SaleDetail::join('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->join('products', 'sale_details.product_id', '=', 'products.id')
            ->whereBetween('sale_details.date', [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth(),
            ])
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('sales.user_id', '=', Auth::user()->id);
                }
            })
            ->where(function ($query) use ($branch_id, $array_branches_id) {
                if ($branch_id !== 0) {
                    return $query->where('sales.branch_id', $branch_id);
                }else{
                    return $query->whereIn('sales.branch_id', $array_branches_id);
                }
            })
            ->select(
                DB::raw('products.name as name'),
                DB::raw('count(*) as total_sales'),
                DB::raw('sum(total) as total'),
            )
            ->groupBy('products.name')
            ->orderBy('total_sales', 'desc')
            ->take(5)
            ->get();

        // Stock Alerts
        $product_branch_data = product_branch::with('branch', 'product' ,'productVariant')
        ->join('products', 'product_branch.product_id', '=', 'products.id')
        ->where('manage_stock', true)
        ->whereRaw('qte <= stock_alert')
        ->where('product_branch.deleted_at', null)
        ->where(function ($query) use ($branch_id, $array_branches_id) {
            if ($branch_id !== 0) {
                return $query->where('product_branch.branch_id', $branch_id);
            }else{
                return $query->whereIn('product_branch.branch_id', $array_branches_id);
            }
        })

        ->take('5')->get();

        $stock_alert = [];
        if ($product_branch_data->isNotEmpty()) {

            foreach ($product_branch_data as $product_branch) {
                if ($product_branch->qte <= $product_branch['product']->stock_alert) {
                    if ($product_branch->product_variant_id !== null) {
                        $item['code'] = $product_branch['productVariant']->name . '-' . $product_branch['product']->code;
                    } else {
                        $item['code'] = $product_branch['product']->code;
                    }
                    $item['quantity'] = $product_branch->qte;
                    $item['name'] = $product_branch['product']->name;
                    $item['branch'] = $product_branch['branch']->name;
                    $item['stock_alert'] = $product_branch['product']->stock_alert;
                    $stock_alert[] = $item;
                }
            }

        }

        //---------------- sales

        $data['today_sales'] = Sale::where('deleted_at', '=', null)
        ->where('date', \Carbon\Carbon::today())
        ->where(function ($query) use ($view_records) {
            if (!$view_records) {
                return $query->where('user_id', '=', Auth::user()->id);
            }
        })
        ->where(function ($query) use ($branch_id, $array_branches_id) {
            if ($branch_id !== 0) {
                return $query->where('branch_id', $branch_id);
            }else{
                return $query->whereIn('branch_id', $array_branches_id);
            }
        })
        ->get(DB::raw('SUM(GrandTotal)  As sum'))
        ->first()->sum;

        $data['today_sales'] = number_format($data['today_sales'], 2, '.', ',');


        //--------------- return_sales

        $data['return_sales'] = SaleReturn::where('deleted_at', '=', null)
        ->where('date', \Carbon\Carbon::today())
        ->where(function ($query) use ($view_records) {
            if (!$view_records) {
                return $query->where('user_id', '=', Auth::user()->id);
            }
        })
        ->where(function ($query) use ($branch_id, $array_branches_id) {
            if ($branch_id !== 0) {
                return $query->where('branch_id', $branch_id);
            }else{
                return $query->whereIn('branch_id', $array_branches_id);
            }
        })
        ->get(DB::raw('SUM(GrandTotal)  As sum'))
        ->first()->sum; 

        $data['return_sales'] = number_format($data['return_sales'], 2, '.', ',');

        //------------------- purchases

        $data['today_purchases'] = Purchase::where('deleted_at', '=', null)
        ->where('date', \Carbon\Carbon::today())
        ->where(function ($query) use ($view_records) {
            if (!$view_records) {
                return $query->where('user_id', '=', Auth::user()->id);
            }
        })
        ->where(function ($query) use ($branch_id, $array_branches_id) {
            if ($branch_id !== 0) {
                return $query->where('branch_id', $branch_id);
            }else{
                return $query->whereIn('branch_id', $array_branches_id);
            }
        })
        ->get(DB::raw('SUM(GrandTotal)  As sum'))
        ->first()->sum;

        $data['today_purchases'] = number_format($data['today_purchases'], 2, '.', ',');

        //------------------------- return_purchases

        $data['return_purchases'] = PurchaseReturn::where('deleted_at', '=', null)
        ->where('date', \Carbon\Carbon::today())
        ->where(function ($query) use ($view_records) {
            if (!$view_records) {
                return $query->where('user_id', '=', Auth::user()->id);
            }
        })
        ->where(function ($query) use ($branch_id, $array_branches_id) {
            if ($branch_id !== 0) {
                return $query->where('branch_id', $branch_id);
            }else{
                return $query->whereIn('branch_id', $array_branches_id);
            }
        })
        ->get(DB::raw('SUM(GrandTotal)  As sum'))
        ->first()->sum;

        $data['return_purchases'] = number_format($data['return_purchases'], 2, '.', ',');

        $last_sales = [];

        //last sales
        $Sales = Sale::with('details', 'client', 'facture','branch')->where('deleted_at', '=', null)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where(function ($query) use ($branch_id, $array_branches_id) {
                if ($branch_id !== 0) {
                    return $query->where('branch_id', $branch_id);
                }else{
                    return $query->whereIn('branch_id', $array_branches_id);
                }
            })
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        foreach ($Sales as $Sale) {

            $item_sale['Ref'] = $Sale['Ref'];
            $item_sale['statut'] = $Sale['statut'];
            $item_sale['client_name'] = $Sale['client']['name'];
            $item_sale['branch_name'] = $Sale['branch']['name'];
            $item_sale['GrandTotal'] = $Sale['GrandTotal'];
            $item_sale['paid_amount'] = $Sale['paid_amount'];
            $item_sale['due'] = $Sale['GrandTotal'] - $Sale['paid_amount'];
            $item_sale['payment_status'] = $Sale['payment_statut'];

            $last_sales[] = $item_sale;
        }

        return response()->json([
            'products' => $products,
            'stock_alert' => $stock_alert,
            'report' => $data,
            'last_sales' => $last_sales,
        ]);

    }

    //----------------- Payment Chart js -----------------------\\

    public function Payment_chart($branch_id, $array_branches_id)
    {

        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');

        // Build an array of the dates we want to show, oldest first
        $dates = collect();
        foreach (range(-6, 0) as $i) {
            $date = Carbon::now()->addDays($i)->format('Y-m-d');
            $dates->put($date, 0);
        }

        $date_range = \Carbon\Carbon::today()->subDays(6);
        // Get the sales counts
        $Payment_Sale = PaymentSale::with('sale')->where('date', '>=', $date_range)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where(function ($query) use ($branch_id, $array_branches_id) {
                if ($branch_id !== 0) {
                    return $query->whereHas('sale', function ($q) use ($array_branches_id, $branch_id) {
                        $q->where('branch_id', $branch_id);
                    });
                }else{
                    return $query->whereHas('sale', function ($q) use ($array_branches_id, $branch_id) {
                        $q->whereIn('branch_id', $array_branches_id);
                    });

                }
            })
            ->groupBy(DB::raw("DATE_FORMAT(date,'%Y-%m-%d')"))
            ->orderBy('date', 'asc')
            ->get([
                DB::raw(DB::raw("DATE_FORMAT(date,'%Y-%m-%d') as date")),
                DB::raw('SUM(montant) AS count'),
            ])
            ->pluck('count', 'date');

        $Payment_Sale_Returns = PaymentSaleReturns::with('SaleReturn')->where('date', '>=', $date_range)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where(function ($query) use ($branch_id, $array_branches_id) {
                if ($branch_id !== 0) {
                    return $query->whereHas('SaleReturn', function ($q) use ($array_branches_id, $branch_id) {
                        $q->where('branch_id', $branch_id);
                    });
                }else{
                    return $query->whereHas('SaleReturn', function ($q) use ($array_branches_id, $branch_id) {
                        $q->whereIn('branch_id', $array_branches_id);
                    });

                }
            })
            ->groupBy(DB::raw("DATE_FORMAT(date,'%Y-%m-%d')"))
            ->orderBy('date', 'asc')
            ->get([
                DB::raw(DB::raw("DATE_FORMAT(date,'%Y-%m-%d') as date")),
                DB::raw('SUM(montant) AS count'),
            ])
            ->pluck('count', 'date');

        $Payment_Purchases = PaymentPurchase::with('purchase')->where('date', '>=', $date_range)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where(function ($query) use ($branch_id, $array_branches_id) {
                if ($branch_id !== 0) {
                    return $query->whereHas('purchase', function ($q) use ($array_branches_id, $branch_id) {
                        $q->where('branch_id', $branch_id);
                    });
                }else{
                    return $query->whereHas('purchase', function ($q) use ($array_branches_id, $branch_id) {
                        $q->whereIn('branch_id', $array_branches_id);
                    });

                }
            })
            ->groupBy(DB::raw("DATE_FORMAT(date,'%Y-%m-%d')"))
            ->orderBy('date', 'asc')
            ->get([
                DB::raw(DB::raw("DATE_FORMAT(date,'%Y-%m-%d') as date")),
                DB::raw('SUM(montant) AS count'),
            ])
            ->pluck('count', 'date');

        $Payment_Purchase_Returns = PaymentPurchaseReturns::with('PurchaseReturn')->where('date', '>=', $date_range)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where(function ($query) use ($branch_id, $array_branches_id) {
                if ($branch_id !== 0) {
                    return $query->whereHas('PurchaseReturn', function ($q) use ($array_branches_id, $branch_id) {
                        $q->where('branch_id', $branch_id);
                    });
                }else{
                    return $query->whereHas('PurchaseReturn', function ($q) use ($array_branches_id, $branch_id) {
                        $q->whereIn('branch_id', $array_branches_id);
                    });

                }
            })
            ->groupBy(DB::raw("DATE_FORMAT(date,'%Y-%m-%d')"))
            ->orderBy('date', 'asc')
            ->get([
                DB::raw(DB::raw("DATE_FORMAT(date,'%Y-%m-%d') as date")),
                DB::raw('SUM(montant) AS count'),
            ])
            ->pluck('count', 'date');

        $Payment_Expense = Expense::where('date', '>=', $date_range)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
            ->where(function ($query) use ($branch_id, $array_branches_id) {
                if ($branch_id !== 0) {
                    return $query->where('branch_id', $branch_id);
                }else{
                    return $query->whereIn('branch_id', $array_branches_id);
                }
            })
            ->groupBy(DB::raw("DATE_FORMAT(date,'%Y-%m-%d')"))
            ->orderBy('date', 'asc')
            ->get([
                DB::raw(DB::raw("DATE_FORMAT(date,'%Y-%m-%d') as date")),
                DB::raw('SUM(amount) AS count'),
            ])
            ->pluck('count', 'date');

        $paymen_recieved = $this->array_merge_numeric_values($Payment_Sale, $Payment_Purchase_Returns);
        $payment_sent = $this->array_merge_numeric_values($Payment_Purchases, $Payment_Sale_Returns, $Payment_Expense);

        $dates_recieved = $dates->merge($paymen_recieved);
        $dates_sent = $dates->merge($payment_sent);

        $data_recieved = [];
        $data_sent = [];
        $days = [];
        foreach ($dates_recieved as $key => $value) {
            $data_recieved[] = $value;
            $days[] = $key;
        }

        foreach ($dates_sent as $key => $value) {
            $data_sent[] = $value;
        }

        return response()->json([
            'payment_sent' => $data_sent,
            'payment_received' => $data_recieved,
            'days' => $days,
        ]);

    }

    //----------------- array merge -----------------------\\

    public function array_merge_numeric_values()
    {
        $arrays = func_get_args();
        $merged = array();
        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                if (!is_numeric($value)) {
                    continue;
                }
                if (!isset($merged[$key])) {
                    $merged[$key] = $value;
                } else {
                    $merged[$key] += $value;
                }
            }
        }
        return $merged;
    }

}
