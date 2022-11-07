<?php

namespace App\Http\Livewire\Erp\Sales\SalesInvoices;

use App\Models\ERP\Inventory\Product;
use App\Models\ERP\Inventory\Warehouse;
use App\Models\ERP\Sales\SalesInvoiceAttachments;
use App\Models\ERP\Sales\Client;
use App\Models\ERP\Settings\GeneralSetting;
use App\Models\ERP\Settings\Tax;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use File;

class EditSalesInvoice extends Component
{
    public $salesInvoice;
    public $clients = [], $client_id = null, $clnt, $inv_number, $countries, $country, $phone_code, $showEditClient = false, $showCreateClient = false, $client_number;
    public $full_name, $phone, $mobile, $street_address, $postal_code, $state, $city;
    public $products = [], $currency_symbol, $warehouses;
    public $taxes = [], $first_tax_ids = [], $second_tax_ids = [], $total_taxes_ids =[];
    public $total_tax_inv, $first_tax_items_val=[], $second_tax_items_val=[], $total_tax_inv_sum = [];
    public $addProduct=[1], $descriptions=[], $unit_prices=[], $quantities=[], $row_total=[];
    public $subtotal;
    public $showAddMoreBtn = false;
    public $discount_inv= null ,$discount = null, $discount_type = null;
    public $down_payment_inv= null ,$down_payment = null, $down_payment_type = null;
    public $deposit_is_paid = false, $deposit_payment_method = null, $deposit_transaction_id = null;
    public $shipping_expense_inv= null ,$shipping_expense = null;
    public $total_inv = null;
    public $due_amount = null;
    public $paid_to_supplier_checkbox = false, $payment_payment_method = null, $payment_transaction_id = null, $paid_to_supplier_inv = null;
    public $payment_status = 1, $receiving_status = 1;

    protected $listeners = ['recordDeleted' => 'deleteFile'];

    public function mount($salesInvoice)
    {
        $this->clients = Client::all();
        $this->taxes = Tax::all();
        $this->products = Product::all();
        $this->warehouses = Warehouse::all();
        $this->salesInvoice = $salesInvoice;
        $this->client_id = $salesInvoice->client_id;
        $this->clnt = Client::find($this->client_id);
        $countriesString = file_get_contents(base_path('public/app-assets/data/countries_ar.json'));
        $this->countries = json_decode($countriesString, true);


        foreach($salesInvoice->salesInvoiceDetails as $index => $details)
        {
            $this->addProduct[$index] = $details;
            $this->descriptions[$index] = $details->description;
            $this->quantities[$index] = $details->quantity;
            $this->unit_prices[$index] = $details->unit_price;

            $this->row_total[$index] = ((int)$this->quantities[$index] * (int)$this->unit_prices[$index]);

            $this->first_tax_ids[$index] = (string)$details->first_tax_id;
            if($this->first_tax_ids[$index]){
                $rate = ($this->taxes->where('id',$this->first_tax_ids[$index])->first()->tax_value)/100;
                $this->first_tax_items_val[$index] = number_format($this->row_total[$index] * $rate, 2, '.', '');
            }

            $this->second_tax_ids[$index] = (string)$details->second_tax_id;
            if($this->second_tax_ids[$index]){
                $rate = ($this->taxes->where('id',$this->second_tax_ids[$index])->first()->tax_value)/100;
                $this->second_tax_items_val[$index] = number_format($this->row_total[$index] * $rate, 2, '.', '');
            }
        }

        $all = array_merge($this->first_tax_ids, $this->second_tax_ids);
        $this->total_taxes_ids = array_keys(array_count_values($all));
//        $this->total_taxes_ids = $this->salesInvoice->salesInvoiceTaxes->pluck('id')->toArray();
        foreach($this->total_taxes_ids as $key => $total_tax) {
            $this->total_tax_inv[$key] = $this->taxes->where('id', $total_tax)->first();
        }
        $allTaxValues = array_merge($this->first_tax_items_val, $this->second_tax_items_val);
        $this->total_tax_inv_sum = array_map(function($key, $val) {return array($key=>$val);}, $all, $allTaxValues);


        $this->subtotal = array_sum($this->row_total);

        $this->discount = $salesInvoice->discount;
        $this->discount_type = $salesInvoice->discount_type;
        $this->discount_inv = ((int)$this->discount/100) * (int)$this->subtotal;

        $this->down_payment = $salesInvoice->down_payment;
        $this->down_payment_type = $salesInvoice->down_payment_type;
        $this->down_payment_inv = ((int)$this->down_payment/100) * (int)$this->subtotal;
        if($this->down_payment != ''){
            $this->deposit_is_paid = true;
        }
        $this->deposit_payment_method = $salesInvoice->deposit_payment_method;
        $this->deposit_transaction_id = $salesInvoice->deposit_transaction_id;

        $this->shipping_expense = $salesInvoice->shipping_expense;
        $this->shipping_expense_inv = $this->shipping_expense;

        $this->calculateDueAmount();

        if($salesInvoice->paid_to_supplier_inv){
            $this->paid_to_supplier_inv = $this->due_amount;
            if($this->paid_to_supplier_inv != ''){
                $this->paid_to_supplier_checkbox = true;
            }
            $this->payment_payment_method = $salesInvoice->payment_payment_method;
            $this->payment_transaction_id = $salesInvoice->payment_transaction_id;
        }

        $this->payment_status = $salesInvoice->payment_status;
        $this->receiving_status = $salesInvoice->receiving_status;
        $this->total_inv = $salesInvoice->total_inv;

        //get general settings currency
        $gs = GeneralSetting::all()->first();
        if(app()->getLocale() == 'ar'){
            $this->currency_symbol = $gs->basic_currency_symbol;
        }else{
            $this->currency_symbol = $gs->basic_currency;
        }

        $this->showAddMoreBtn = true;

    }

    public function updatedClientId($value)
    {
        $this->showCreateClient = false;
        $this->emptyClientForm();
        $this->clnt = Client::find($value);
        $this->clientFormData();
    }
    public function showEditClient()
    {
        $this->showCreateClient = false;
        $this->emptyClientForm();
        $this->showEditClient = true;
        $this->clientFormData();
    }
    public function cancelEditClient()
    {
        $this->showEditClient = false;
        $this->emptyClientForm();
        $this->resetErrorBag();
    }
    public function showCreateClient()
    {
        $this->showEditClient = false;
        $this->emptyClientForm();
        $this->client_id = null;
        $this->showCreateClient = true;
        $this->full_name = trans('applang.client') . ' #' .$this->salesInvoice->number;
    }
    public function cancelCreateClient()
    {
        $this->showCreateClient = false;
        $this->emptyClientForm();
        $this->resetErrorBag();
    }
    public function clientFormData()
    {
        if($this->clnt != null) {
            $this->full_name = $this->clnt->full_name;
            $this->phone = $this->clnt->phone;
            $this->mobile = $this->clnt->mobile;
            $this->street_address = $this->clnt->street_address;
            $this->postal_code = $this->clnt->postal_code;
            $this->state = $this->clnt->state;
            $this->city = $this->clnt->city;
            $this->country = $this->clnt->country;
            $this->phone_code = $this->clnt->phone_code;
        }
    }
    public function emptyClientForm()
    {
        $this->full_name = '';
        $this->phone = '';
        $this->mobile = '';
        $this->street_address = '';
        $this->postal_code = '';
        $this->state = '';
        $this->city = '';
        $this->country = '';
        $this->phone_code = '';
    }
    public function updatedCountry($value)
    {
        $countriesString = file_get_contents(base_path('public/app-assets/data/countries_ar.json'));
        $countries = json_decode($countriesString, true);
        foreach ($countries as $key => $country) {
            if($value == $key){
                $this->phone_code = '+' . $country['phone_code'];
            }
        }
    }
    protected $rules = [
        'full_name' => 'required|min:3',
    ];
    public function saveNewClient()
    {
        $this->validate();

        $data = [
            'full_name' => $this->full_name,
            'country' => $this->country,
            'phone'  => $this->phone,
            'mobile'  => $this->mobile,
            'street_address'  => $this->street_address,
            'postal_code'  => $this->postal_code,
            'state'  => $this->state,
            'city'  => $this->city,
            'created_by' => auth()->user()->id
        ];

        Client::create($data);
        $this->clients = Client::all();
        $this->client_id = Client::latest('id')->first()->id;
        $this->clnt = Client::find($this->client_id);
        $this->showCreateClient = false;
    }
    public function saveEditClient()
    {
        $this->validate();
        $client = Client::find($this->client_id);
        $data = [
            'full_name' => $this->full_name,
            'country' => $this->country,
            'phone'  => $this->phone,
            'mobile'  => $this->mobile,
            'street_address'  => $this->street_address,
            'postal_code'  => $this->postal_code,
            'state'  => $this->state,
            'city'  => $this->city,
            'created_by' => auth()->user()->id
        ];
        $client->update($data);
        $this->clients = Client::all();
        $this->client_id = $client->id;
        $this->clnt = Client::find($this->client_id);
        $this->showEditClient = false;
        $this->emptyClientForm();
    }



    public function addMoreProduct()
    {
        $this->addProduct[] = count($this->addProduct) + 1;
        $this->showAddMoreBtn = false;
    }

    public function removeProduct($index)
    {
        unset($this->addProduct[$index]);
        unset($this->descriptions[$index]);
        unset($this->unit_prices[$index]);
        unset($this->quantities[$index]);
        unset($this->first_tax_ids[$index]);
        unset($this->second_tax_ids[$index]);
        unset($this->first_tax_items_val[$index]);
        unset($this->second_tax_items_val[$index]);
        unset($this->total_tax_inv_sum[$index]);
        unset($this->row_total[$index]);

        $this->addProduct =array_values($this->addProduct);
        $this->descriptions =array_values($this->descriptions);
        $this->unit_prices =array_values($this->unit_prices);
        $this->quantities =array_values($this->quantities);
        $this->first_tax_ids =array_values($this->first_tax_ids);
        $this->second_tax_ids =array_values($this->second_tax_ids);
        $this->first_tax_items_val = array_values($this->first_tax_items_val);
        $this->second_tax_items_val = array_values($this->second_tax_items_val);
        $this->total_tax_inv_sum = array_values($this->total_tax_inv_sum);
        $this->row_total = array_values($this->row_total);

        $this->subtotal = array_sum($this->row_total);


        $all = array_merge($this->first_tax_ids, $this->second_tax_ids);
        $this->total_taxes_ids = array_keys(array_count_values($all));
        foreach($this->total_taxes_ids as $key => $total_tax) {
            $this->total_tax_inv[$key] = $this->taxes->where('id', $total_tax)->first();
        }
        $allTaxValues = array_merge($this->first_tax_items_val, $this->second_tax_items_val);
        $this->total_tax_inv_sum = array_map(function($key, $val) {return array($key=>$val);}, $all, $allTaxValues);

        $this->showAddMoreBtn = true;

        $this->calculateDueAmount();
    }

    public function updated ($key, $value)
    {
        $parts = explode(".", $key);
        if(count($parts) === 3 && $parts[0] === "addProduct") {
            if($value){
                $description = $this->products->where('id', $value)->first()->description;
                if($description) {
                    $this->descriptions[$parts[1]] = $description;
                }

                $unit_price = $this->products->where('id', $value)->first()->sell_price;
                $this->unit_prices[$parts[1]] =  $unit_price;

                $first_tax_id = $this->products->where('id', $value)->first()->first_tax_id;
                $this->first_tax_ids[$parts[1]] =  (string)$first_tax_id;

                $second_tax_id = $this->products->where('id', $value)->first()->second_tax_id;
                $this->second_tax_ids[$parts[1]] =  (string)$second_tax_id;

                if(isset($this->quantities[$parts[1]])){
                    $this->row_total[$parts[1]] = ((int)$this->quantities[$parts[1]] * (int)$this->unit_prices[$parts[1]]);

                    if($this->first_tax_ids[$parts[1]]){
                        $rate = ($this->taxes->where('id',$this->first_tax_ids[$parts[1]])->first()->tax_value)/100;
                        $this->first_tax_items_val[$parts[1]] = number_format($this->row_total[$parts[1]] * $rate, 2, '.', '');
                    }

                    if($this->second_tax_ids[$parts[1]])
                    {
                        $rate = ($this->taxes->where('id',$this->second_tax_ids[$parts[1]])->first()->tax_value)/100;
                        $this->second_tax_items_val[$parts[1]] = number_format($this->row_total[$parts[1]] * $rate, 2, '.', '');
                    }else{
                        $this->second_tax_ids[$parts[1]] =  '';
                    }

                    $this->subtotal = array_sum($this->row_total);

                    $all = array_merge($this->first_tax_ids, $this->second_tax_ids);
                    $this->total_taxes_ids = array_keys(array_count_values($all));
                    foreach($this->total_taxes_ids as $key => $total_tax) {
                        $this->total_tax_inv[$key] = $this->taxes->where('id', $total_tax)->first();
                    }
                    $allTaxValues = array_merge($this->first_tax_items_val, $this->second_tax_items_val);
                    $this->total_tax_inv_sum = array_map(function($key, $val) {return array($key=>$val);}, $all, $allTaxValues);
                }

                foreach($this->second_tax_ids as $key => $second_tax){
                    if($this->second_tax_ids[$key] == $this->first_tax_ids[$key]){
                        $second_tax = '';
                        $this->second_tax_ids[$key] = '';
                    }
                    if($second_tax && isset($this->quantities[$key])) {
                        $rate = ($this->taxes->where('id',$this->second_tax_ids[$key])->first()->tax_value)/100;
                        $this->second_tax_items_val[$key] = number_format($this->row_total[$key] * $rate, 2, '.', '');
                    }else{
                        $this->second_tax_items_val[$key] = '';
                    }
                }
            }else{
                if(count($this->addProduct) > 1){
                    unset($this->addProduct[$parts[1]]);
                    unset($this->descriptions[$parts[1]]);
                    unset($this->unit_prices[$parts[1]]);
                    unset($this->quantities[$parts[1]]);
                    unset($this->first_tax_ids[$parts[1]]);
                    unset($this->second_tax_ids[$parts[1]]);
                    unset($this->first_tax_items_val[$parts[1]]);
                    unset($this->second_tax_items_val[$parts[1]]);
                    unset($this->total_tax_inv_sum[$parts[1]]);
                    unset($this->row_total[$parts[1]]);

                    $this->addProduct =array_values($this->addProduct);
                    $this->descriptions =array_values($this->descriptions);
                    $this->unit_prices =array_values($this->unit_prices);
                    $this->quantities =array_values($this->quantities);
                    $this->first_tax_ids =array_values($this->first_tax_ids);
                    $this->second_tax_ids =array_values($this->second_tax_ids);
                    $this->first_tax_items_val = array_values($this->first_tax_items_val);
                    $this->second_tax_items_val = array_values($this->second_tax_items_val);
                    $this->total_tax_inv_sum = array_values($this->total_tax_inv_sum);
                    $this->row_total = array_values($this->row_total);

                    $this->subtotal = array_sum($this->row_total);

                    $all = array_merge($this->first_tax_ids, $this->second_tax_ids);
                    $this->total_taxes_ids = array_keys(array_count_values($all));
                    foreach($this->total_taxes_ids as $key => $total_tax) {
                        $this->total_tax_inv[$key] = $this->taxes->where('id', $total_tax)->first();
                    }
                    $allTaxValues = array_merge($this->first_tax_items_val, $this->second_tax_items_val);
                    $this->total_tax_inv_sum = array_map(function($key, $val) {return array($key=>$val);}, $all, $allTaxValues);
                    $this->showAddMoreBtn = true;
                }else{
                    unset($this->descriptions[$parts[1]]);
                    unset($this->unit_prices[$parts[1]]);
                    unset($this->quantities[$parts[1]]);
                    unset($this->first_tax_ids[$parts[1]]);
                    unset($this->second_tax_ids[$parts[1]]);
                    unset($this->first_tax_items_val[$parts[1]]);
                    unset($this->second_tax_items_val[$parts[1]]);
                    unset($this->total_tax_inv_sum[$parts[1]]);
                    unset($this->row_total[$parts[1]]);

                    $this->descriptions =array_values($this->descriptions);
                    $this->unit_prices =array_values($this->unit_prices);
                    $this->quantities =array_values($this->quantities);
                    $this->first_tax_ids =array_values($this->first_tax_ids);
                    $this->second_tax_ids =array_values($this->second_tax_ids);
                    $this->first_tax_items_val = array_values($this->first_tax_items_val);
                    $this->second_tax_items_val = array_values($this->second_tax_items_val);
                    $this->total_tax_inv_sum = array_values($this->total_tax_inv_sum);
                    $this->row_total = array_values($this->row_total);

                    $this->subtotal = array_sum($this->row_total);

                    $all = array_merge($this->first_tax_ids, $this->second_tax_ids);
                    $this->total_taxes_ids = array_keys(array_count_values($all));
                    foreach($this->total_taxes_ids as $key => $total_tax) {
                        $this->total_tax_inv[$key] = $this->taxes->where('id', $total_tax)->first();
                    }
                    $allTaxValues = array_merge($this->first_tax_items_val, $this->second_tax_items_val);
                    $this->total_tax_inv_sum = array_map(function($key, $val) {return array($key=>$val);}, $all, $allTaxValues);
                    $this->showAddMoreBtn = true;
                }
            }
        }

        $this->calculateDueAmount();
    }

    public function updatedSupplierId($value)
    {
        $this->supplier_id = '';
        $this->supp = Supplier::find($value);
        $this->supplier_id = $this->supp->id ?? '';
    }

    public function updatedFirstTaxIds($value)
    {
        foreach($this->first_tax_ids as $key => $first_tax){
            if($this->second_tax_ids[$key] == $this->first_tax_ids[$key]){
                $first_tax = '';
                $this->first_tax_ids[$key] = '';
            }
            if($first_tax && isset($this->quantities[$key])) {
                $rate = ($this->taxes->where('id', $this->first_tax_ids[$key])->first()->tax_value) / 100;
                $this->first_tax_items_val[$key] = number_format($this->row_total[$key] * $rate, 2, '.', '');
            }else{
                $this->first_tax_items_val[$key] = '';
            }
        }

        foreach($this->second_tax_ids as $key => $second_tax){
            if($this->second_tax_ids[$key] == $this->first_tax_ids[$key]){
                $second_tax = '';
                $this->second_tax_ids[$key] = '';
            }
            if($second_tax && isset($this->quantities[$key])) {
                $rate = ($this->taxes->where('id',$this->second_tax_ids[$key])->first()->tax_value)/100;
                $this->second_tax_items_val[$key] = number_format($this->row_total[$key] * $rate, 2, '.', '');
            }else{
                $this->second_tax_items_val[$key] = '';
            }
        }

        $all = array_merge($this->first_tax_ids, $this->second_tax_ids);
        $this->total_taxes_ids = array_keys(array_count_values($all));
        foreach($this->total_taxes_ids as $key => $total_tax) {
            $this->total_tax_inv[$key] = $this->taxes->where('id', $total_tax)->first();
        }
        $allTaxValues = array_merge($this->first_tax_items_val, $this->second_tax_items_val);
        $this->total_tax_inv_sum = array_map(function($key, $val) {return array($key=>$val);}, $all, $allTaxValues);

        $this->calculateDueAmount();
    }

    public function updatedSecondTaxIds($value)
    {
        foreach($this->second_tax_ids as $key => $second_tax){
            if($this->second_tax_ids[$key] == $this->first_tax_ids[$key]){
                $second_tax = '';
                $this->second_tax_ids[$key] = '';
            }
            if($second_tax && isset($this->quantities[$key])) {
                $rate = ($this->taxes->where('id',$this->second_tax_ids[$key])->first()->tax_value)/100;
                $this->second_tax_items_val[$key] = number_format($this->row_total[$key] * $rate, 2, '.', '');
            }else{
                $this->second_tax_items_val[$key] = '';
            }
        }

        $all = array_merge($this->first_tax_ids, $this->second_tax_ids);
        $this->total_taxes_ids = array_keys(array_count_values($all));
        foreach($this->total_taxes_ids as $key => $total_tax) {
            $this->total_tax_inv[$key] = $this->taxes->where('id', $total_tax)->first();
        }
        $allTaxValues = array_merge($this->first_tax_items_val, $this->second_tax_items_val);
        $this->total_tax_inv_sum = array_map(function($key, $val) {return array($key=>$val);}, $all, $allTaxValues);

        $this->calculateDueAmount();
    }

    public function updatedQuantities($value)
    {
        if($value){
            foreach ($this->quantities as $key => $product)
            {
                $this->row_total[$key] = ((int)$this->quantities[$key] * (int)$this->unit_prices[$key]);

                if($this->first_tax_ids[$key]){
                    $rate = ($this->taxes->where('id',$this->first_tax_ids[$key])->first()->tax_value)/100;
                    $this->first_tax_items_val[$key] = number_format($this->row_total[$key] * $rate, 2, '.', '');
                }

                if($this->second_tax_ids[$key])
                {
                    $rate = ($this->taxes->where('id',$this->second_tax_ids[$key])->first()->tax_value)/100;
                    $this->second_tax_items_val[$key] = number_format($this->row_total[$key] * $rate, 2, '.', '');
                }
            }

            $this->subtotal = array_sum($this->row_total);

            $all = array_merge($this->first_tax_ids, $this->second_tax_ids);
            $this->total_taxes_ids = array_keys(array_count_values($all));
            foreach($this->total_taxes_ids as $key => $total_tax) {
                $this->total_tax_inv[$key] = $this->taxes->where('id', $total_tax)->first();
            }
            $allTaxValues = array_merge($this->first_tax_items_val, $this->second_tax_items_val);
            $this->total_tax_inv_sum = array_map(function($key, $val) {return array($key=>$val);}, $all, $allTaxValues);

            $this->showAddMoreBtn = true;
        }
        else{
            foreach ($this->quantities as $key => $product){
                $this->row_total[$key] = ((int)$this->quantities[$key] * (int)$this->unit_prices[$key]);

                if($this->first_tax_ids[$key]){
                    $rate = ($this->taxes->where('id',$this->first_tax_ids[$key])->first()->tax_value)/100;
                    $this->first_tax_items_val[$key] = number_format($this->row_total[$key] * $rate, 2, '.', '');
                }

                if($this->second_tax_ids[$key])
                {
                    $rate = ($this->taxes->where('id',$this->second_tax_ids[$key])->first()->tax_value)/100;
                    $this->second_tax_items_val[$key] = number_format($this->row_total[$key] * $rate, 2, '.', '');
                }
            }

            $this->subtotal = array_sum($this->row_total);

            $all = array_merge($this->first_tax_ids, $this->second_tax_ids);
            $this->total_taxes_ids = array_keys(array_count_values($all));
            foreach($this->total_taxes_ids as $key => $total_tax) {
                $this->total_tax_inv[$key] = $this->taxes->where('id', $total_tax)->first();
            }
            $allTaxValues = array_merge($this->first_tax_items_val, $this->second_tax_items_val);
            $this->total_tax_inv_sum = array_map(function($key, $val) {return array($key=>$val);}, $all, $allTaxValues);

            $this->showAddMoreBtn = false;

            $this->discount = '';
            $this->discount_type = '';
            $this->discount_inv = '';
            $this->discount_type = '';
            $this->down_payment = '';
            $this->down_payment_inv = '';
            $this->down_payment_type = '';
        }

        $this->calculateDueAmount();
    }

    public function updatedUnitPrices($value)
    {
        if($value){
            foreach ($this->unit_prices as $key => $price)
            {
                $this->row_total[$key] = ((int)$this->quantities[$key] * (int)$this->unit_prices[$key]);

                if($this->first_tax_ids[$key]){
                    $rate = ($this->taxes->where('id',$this->first_tax_ids[$key])->first()->tax_value)/100;
                    $this->first_tax_items_val[$key] = number_format($this->row_total[$key] * $rate, 2, '.', '');
                }

                if($this->second_tax_ids[$key])
                {
                    $rate = ($this->taxes->where('id',$this->second_tax_ids[$key])->first()->tax_value)/100;
                    $this->second_tax_items_val[$key] = number_format($this->row_total[$key] * $rate, 2, '.', '');
                }
            }

            $this->subtotal = array_sum($this->row_total);

            $all = array_merge($this->first_tax_ids, $this->second_tax_ids);
            $this->total_taxes_ids = array_keys(array_count_values($all));
            foreach($this->total_taxes_ids as $key => $total_tax) {
                $this->total_tax_inv[$key] = $this->taxes->where('id', $total_tax)->first();
            }
            $allTaxValues = array_merge($this->first_tax_items_val, $this->second_tax_items_val);
            $this->total_tax_inv_sum = array_map(function($key, $val) {return array($key=>$val);}, $all, $allTaxValues);

            $this->showAddMoreBtn = true;
        }
        else
        {
            foreach ($this->unit_prices as $key => $price)
            {
                $this->row_total[$key] = ((int)$this->quantities[$key] * (int)$this->unit_prices[$key]);

                if($this->first_tax_ids[$key]){
                    $rate = ($this->taxes->where('id',$this->first_tax_ids[$key])->first()->tax_value)/100;
                    $this->first_tax_items_val[$key] = number_format($this->row_total[$key] * $rate, 2, '.', '');
                }

                if($this->second_tax_ids[$key])
                {
                    $rate = ($this->taxes->where('id',$this->second_tax_ids[$key])->first()->tax_value)/100;
                    $this->second_tax_items_val[$key] = number_format($this->row_total[$key] * $rate, 2, '.', '');
                }
            }

            $this->subtotal = array_sum($this->row_total);

            $all = array_merge($this->first_tax_ids, $this->second_tax_ids);
            $this->total_taxes_ids = array_keys(array_count_values($all));
            foreach($this->total_taxes_ids as $key => $total_tax) {
                $this->total_tax_inv[$key] = $this->taxes->where('id', $total_tax)->first();
            }
            $allTaxValues = array_merge($this->first_tax_items_val, $this->second_tax_items_val);
            $this->total_tax_inv_sum = array_map(function($key, $val) {return array($key=>$val);}, $all, $allTaxValues);

            $this->showAddMoreBtn = false;

            $this->discount = '';
            $this->discount_type = '';
            $this->discount_inv = '';
            $this->discount_type = '';
            $this->down_payment = '';
            $this->down_payment_inv = '';
            $this->down_payment_type = '';
        }

        $this->calculateDueAmount();
    }

    public function updatedDiscount($value)
    {
        $this->discount = $value;

        if($this->down_payment_type != null && $this->subtotal != ''){
            if($this->discount_type == 1)
            {
                $this->discount_inv = ((int)$this->discount/100) * (int)$this->subtotal;
            }elseif ($this->discount_type == 0)
            {
                $this->discount_inv = (int)$this->discount;
            }else{
//                $this->discount = '';
                $this->discount_inv = '';
                $this->discount_type = '';
            }
        }else{
//            $this->discount = '';
            $this->discount_inv = '';
            $this->discount_type = '';
        }

        $this->calculateDueAmount();
    }

    public function updatedDiscountType($value)
    {
        if($value == 1 && $this->subtotal != '')
        {
            $this->discount_inv = ((int)$this->discount/100) * (int)$this->subtotal;
        }
        elseif ($value == 0 && $this->subtotal != '')
        {
            $this->discount_inv = (int)$this->discount;
        }
        else{
            $this->discount = '';
            $this->discount_inv = '';
            $this->discount_type = '';
        }

        $this->calculateDueAmount();
    }

    public function updatedDownPayment($value)
    {
        $this->down_payment = $value;

        if($this->deposit_is_paid == true && $this->subtotal != '' && $this->down_payment != '')
        {
            if($this->down_payment_type != null ) {
                if($this->down_payment_type == 1)
                {
                    $this->down_payment_inv = ((int)$this->down_payment/100) * (int)$this->subtotal;
                }elseif ($this->down_payment_type == 0)
                {
                    $this->down_payment_inv = (int)$this->down_payment;
                }else{
                    $this->down_payment = '';
                    $this->down_payment_inv = '';
                    $this->down_payment_type = '';
                    $this->deposit_payment_method = '';
                    $this->deposit_transaction_id = '';
                }
            }
        }
        else{
            $this->down_payment = '';
            $this->down_payment_inv = '';
            $this->down_payment_type = '';
            $this->deposit_payment_method = '';
            $this->deposit_transaction_id = '';
        }

        $this->calculateDueAmount();
    }

    public function updatedDownPaymentType($value)
    {
        if($this->deposit_is_paid == true && $this->subtotal != '')
        {

            if($value == 1 && $this->down_payment != '')
            {
                $this->down_payment_inv = ((int)$this->down_payment/100) * (int)$this->subtotal;
            }
            elseif ($value == 0 && $this->down_payment != '')
            {
                $this->down_payment_inv = (int)$this->down_payment;
            }
            else{
                $this->down_payment = '';
                $this->down_payment_inv = '';
                $this->down_payment_type = '';
                $this->deposit_payment_method = '';
                $this->deposit_transaction_id = '';
            }

        }
        else
        {
            $this->down_payment = '';
            $this->down_payment_inv = '';
            $this->down_payment_type = '';
            $this->deposit_payment_method = '';
            $this->deposit_transaction_id = '';
        }

        $this->calculateDueAmount();
    }

    public function updatedDepositIsPaid($value)
    {
        if($value == true)
        {
            if($this->down_payment_type != null && $this->down_payment != '' && $this->subtotal != '')
            {
                if($this->down_payment_type == 1)
                {
                    $this->down_payment_inv = ((int)$this->down_payment/100) * (int)$this->subtotal;
                }
                elseif ($this->down_payment_type == 0)
                {
                    $this->down_payment_inv = (int)$this->down_payment;
                }
                else
                {
                    $this->down_payment = '';
                    $this->down_payment_inv = '';
                    $this->down_payment_type = '';
                    $this->deposit_payment_method = '';
                    $this->deposit_transaction_id = '';
                }
            }
            else
            {
                $this->down_payment = '';
                $this->down_payment_inv = '';
                $this->down_payment_type = '';
                $this->deposit_payment_method = '';
                $this->deposit_transaction_id = '';
            }
        }
        else
        {
            $this->down_payment = '';
            $this->down_payment_inv = '';
            $this->down_payment_type = '';
            $this->deposit_payment_method = '';
            $this->deposit_transaction_id = '';
        }
        $this->calculateDueAmount();
    }

    public function updatedDepositPaymentMethod($value)
    {
        if($this->down_payment != '' && $this->down_payment_type != '')
        {
            $this->deposit_payment_method = $value;
        }else{
            $this->deposit_payment_method = '';
        }
    }

    public function updatedDepositTransactionId($value)
    {
        if($this->down_payment != '' && $this->down_payment_type != '' && $this->deposit_payment_method != '')
        {
            $this->deposit_transaction_id = $value;
        }else{
            $this->deposit_transaction_id = '';
        }
    }

    public function updatedShippingExpense($value)
    {
        $this->shipping_expense = $value;
        if($this->subtotal != ''){
            $this->shipping_expense_inv = $this->shipping_expense;
        }else{
            $this->shipping_expense = '';
            $this->shipping_expense_inv = '';
        }

        $this->calculateDueAmount();
    }

    public function calculateDueAmount()
    {
        if($this->subtotal != ''){
            $subtotal = (int)$this->subtotal ?? 0;
            $discount = (int)$this->discount_inv ?? 0;
            $allTaxSum = (int)array_sum(array_merge($this->first_tax_items_val, $this->second_tax_items_val)) ?? 0;
            $shippingExpenses = (int)$this->shipping_expense_inv ?? 0;
            $downPayment = (int)$this->down_payment_inv ?? 0;

            $total_inv = (($subtotal-$discount)+($allTaxSum+$shippingExpenses));
            $dueAmount = (($subtotal-$discount)+($allTaxSum+$shippingExpenses))-$downPayment;

            $this->total_inv = $total_inv;
            $this->due_amount = $dueAmount;

            if($downPayment > 0){
                $this->payment_status = 2;
            }else{
                $this->payment_status = 1;
            }

            if($this->salesInvoice->paid_to_supplier_inv != ''){
                $this->paid_to_supplier_inv = $this->due_amount;
                $this->payment_status = 3;
            }
        }
    }

    public function updatedPaidToSupplierCheckbox($value)
    {
        if($value == true && $this->due_amount != '')
        {
            $this->down_payment = '';
            $this->down_payment_inv = '';
            $this->down_payment_type = '';
            $this->deposit_payment_method = '';
            $this->deposit_transaction_id = '';
            $this->calculateDueAmount();

            $this->paid_to_supplier_inv = $this->due_amount;
            $this->payment_status = 3;
        }
        elseif($value == false && $this->due_amount != '')
        {
            $this->paid_to_supplier_inv = '';
            $this->payment_payment_method = '';
            $this->payment_transaction_id = '';
            $this->payment_status = 1;
            $this->calculateDueAmount();
        }
    }

    public function updatedPaymentPaymentMethod($value)
    {
        if($this->due_amount != '')
        {
            $this->payment_payment_method = $value;
            $this->payment_status = 3;
        }else{
            $this->payment_payment_method = '';
            $this->payment_status = 1;
        }
    }

    public function updatedPaymentTransactionId($value)
    {
        if($this->due_amount != '')
        {
            $this->payment_transaction_id = $value;
            $this->payment_status = 3;
        }else{
            $this->payment_transaction_id = '';
            $this->payment_status = 1;
        }
    }

    public function confirmDelete($file_id, $file_name)
    {
        $this->dispatchBrowserEvent('Swal:DeleteRecordConfirmation', [
            'title' => "<span>".trans('applang.swal_confirm_delete_msg')."<span class='text-danger'>(".$file_name .")</span>"."</span>",
            'id'    => $file_id
        ]);
    }

    public function deleteFile($file_id)
    {
        $file = salesInvoiceAttachments::find($file_id); //Single Delete

        $salesInvoice = $file->salesInvoice;
        $folder_date = $salesInvoice->created_at->format('m-Y');
        $filePath = 'uploads/sales_invoices_attachments/'. $folder_date.'/'.$salesInvoice->inv_number.'/'.$file->attachments;
        $invoiceNumberFolder = Storage::disk('public_uploads')->path('sales_invoices_attachments/'.$folder_date.'/'.$salesInvoice->inv_number);
        $dateFolder = Storage::disk('public_uploads')->path('sales_invoices_attachments/'.$folder_date);

        File::delete(public_path($filePath));

        $FileSystem = new Filesystem();
        //Check if the invoiceNumberFolder exists.
        if ($FileSystem->exists($invoiceNumberFolder)) {
            // Get all files in this invoiceNumberFolder.
            $files = $FileSystem->files($invoiceNumberFolder);
            // Check if invoiceNumberFolder is empty.
            if (empty($files)) {
                // Yes, delete the invoiceNumberFolder.
                $FileSystem->deleteDirectory($invoiceNumberFolder);
            }
        }

        //Check if the dateFolder exists.
        if ($FileSystem->exists($dateFolder )) {
            // Get all folders in this dateFolder.
            $folders = $FileSystem->directories($dateFolder);
            // Check if dateFolder is empty.
            if (empty($folders)) {
                // Yes, delete the dateFolder.
                $FileSystem->deleteDirectory($dateFolder);
            }
        }
        $file->delete();
        $this->dispatchBrowserEvent('refresh-page');
    }

    public function render()
    {
        return view('livewire.erp.sales.sales-invoices.edit-sales-invoice');
    }
}
