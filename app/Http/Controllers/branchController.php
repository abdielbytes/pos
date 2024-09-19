<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\product_branch;
use App\Models\branch;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;

class branchController extends Controller
{

    //----------- GET ALL  branch --------------\\

    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', branch::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;

        $branches = branch::where('deleted_at', '=', null)

        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('name', 'LIKE', "%{$request->search}%")
                        ->orWhere('mobile', 'LIKE', "%{$request->search}%")
                        ->orWhere('country', 'LIKE', "%{$request->search}%")
                        ->orWhere('city', 'LIKE', "%{$request->search}%")
                        ->orWhere('zip', 'LIKE', "%{$request->search}%")
                        ->orWhere('email', 'LIKE', "%{$request->search}%");
                });
            });
        $totalRows = $branches->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $branches = $branches->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        return response()->json([
            'branches' => $branches,
            'totalRows' => $totalRows,
        ]);
    }

    //----------- Store new branch --------------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', branch::class);

        request()->validate([
            'name' => 'required',
        ]);

        \DB::transaction(function () use ($request) {

            $branch          = new branch;
            $branch->name    = $request['name'];
            $branch->mobile  = $request['mobile'];
            $branch->country = $request['country'];
            $branch->city    = $request['city'];
            $branch->zip     = $request['zip'];
            $branch->email   = $request['email'];
            $branch->save();

            $products = Product::where('deleted_at', '=', null)->get(['id','type']);

            if ($products) {
                foreach ($products as $product) {
                    $product_branch = [];
                    $Product_Variants = ProductVariant::where('product_id', $product->id)
                        ->where('deleted_at', null)
                        ->get();

                    if ($Product_Variants->isNotEmpty()) {
                        foreach ($Product_Variants as $product_variant) {

                            $product_branch[] = [
                                'product_id'         => $product->id,
                                'branch_id'       => $branch->id,
                                'product_variant_id' => $product_variant->id,
                                'manage_stock'       => $product->type == 'is_service'?0:1,
                            ];
                        }
                    } else {
                        $product_branch[] = [
                            'product_id'         => $product->id,
                            'branch_id'       => $branch->id,
                            'product_variant_id' => null,
                            'manage_stock'       => $product->type == 'is_service'?0:1,
                        ];
                    }

                    product_branch::insert($product_branch);
                }
            }

        }, 10);

        return response()->json(['success' => true]);
    }



    //-----------Update branch --------------\\

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', branch::class);

        request()->validate([
            'name' => 'required',
        ]);

        branch::whereId($id)->update([
            'name' => $request['name'],
            'mobile' => $request['mobile'],
            'country' => $request['country'],
            'city' => $request['city'],
            'zip' => $request['zip'],
            'email' => $request['email'],
        ]);
        return response()->json(['success' => true]);
    }

    //----------- Delete  branch --------------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', branch::class);

        \DB::transaction(function () use ($id) {

            branch::whereId($id)->update([
                'deleted_at' => Carbon::now(),
            ]);

            product_branch::where('branch_id', $id)->update([
                'deleted_at' => Carbon::now(),
            ]);

        }, 10);

        return response()->json(['success' => true]);
    }

    //-------------- Delete by selection  ---------------\\

    public function delete_by_selection(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'delete', branch::class);

        \DB::transaction(function () use ($request) {
            $selectedIds = $request->selectedIds;
            foreach ($selectedIds as $branch_id) {
                branch::whereId($branch_id)->update([
                    'deleted_at' => Carbon::now(),
                ]);

                product_branch::where('branch_id', $branch_id)->update([
                    'deleted_at' => Carbon::now(),
                ]);
            }

        }, 10);

        return response()->json(['success' => true]);
    }

    //----------- GET ALL  branch --------------\\

    public function Get_branches()
    {
        $branches = branch::where('deleted_at', '=', null)->get();
        return response()->json($branches);
    }

}
