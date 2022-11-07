<!DOCTYPE html>
<html lang="{{app()->getLocale() == 'ar' ? 'ar' : 'en'}}">
<head>
    <meta charset="utf-8" />
    <title>{{trans('applang.receipt_list')}}: # {{$salesInvoice->number}}</title>

    <style>
        .invoice-box {
            max-width: 1000px;
            margin: auto;
            /*padding: 30px;*/
            font-size: 14px;
            line-height: 24px;
            font-family: 'XBRiyaz', sans-serif !important;
            color: #555;
        }

        .invoice-box table {
            width: 100%;
            line-height: inherit;
            text-align: left;
            font-size: 14px;
        }

        .invoice-box table td {
            padding: 5px;
            vertical-align: center;
        }

        /*        .invoice-box table tr td:nth-child(2) {
                    text-align: left;
                }*/

        .invoice-box table thead{
            text-align: start !important;
        }


        .invoice-box table tr.top table td {
            /*padding-bottom: 20px;*/
        }

        .invoice-box table tr.top table td.title-ltr {
            font-size: 45px;
            line-height: 45px;
            color: #333;
            text-align: right;
        }
        .invoice-box table tr.top table td.title-rtl {
            font-size: 45px;
            line-height: 45px;
            color: #333;
            text-align: left;
        }

        @media only screen and (max-width: 600px) {
            .invoice-box table tr.top table td {
                width: 100%;
                display: block;
                text-align: center;
            }
        }

        /** RTL **/
        .invoice-box.rtl {
            direction: rtl;
            font-family: 'XBRiyaz', sans-serif !important;
        }

        .invoice-box.rtl table {
            text-align: right;
            font-size: 14px;
        }

        /*        .invoice-box.rtl table.inv-header tr td:nth-child(2) {
                    text-align: right;
                }*/

        .invoice-box tr.top table.inv-header{
            border-bottom: 1px solid #9d9d9d !important;
        }

        .invoice-box tr.top table.inv-header tr td.inv-type{
            width: 70%;
            font-size: 30px;
        }

        .invoice-box table.details-table{
            border: 1px solid #333333;
        }
        .invoice-box table.details-table tr, .invoice-box table.details-table td{
            border: 1px solid #333333;
        }

        .invoice-box table tr.details-head td{
            font-size: 15px;
            background-color: #D9D9D9;
        }
        .invoice-box table.details-table tfoot tr td{
            background-color: #D9D9D9;
        }

        .img-container {
            width: 25%;
        }

    </style>
</head>

<body>
<div class="invoice-box {{app()->getLocale() == 'ar' ? 'rtl' : ''}}">
    <table cellpadding="0" cellspacing="0">
        <tr class="top">
            <td colspan="6">
                <table class="inv-header">
                    <tr>
                        <td class="inv-type">
                            <span>{{trans('applang.receipt_list')}}</span>
                        </td>
                        <td>
                            <b>{{$companyData->business_name}}</b>
                            <div>{{$companyData->first_name}} {{$companyData->last_name}}</div>
                            <div>{{$companyData->email}}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table>
        <tr>
            <td>
                <div><b>{{trans('applang.invoice')}}</b> # {{$salesInvoice->inv_number}}</div>
            </td>
        </tr>
    </table>

    <br>
    <br>

    <table>
        <tr>
            <td width="70%">
                <b>{{trans('applang.warehouse')}}</b>
                <div>{{$salesInvoice->warehouse->name}}</div>
                <div>{{$salesInvoice->warehouse->shipping_address}}</div>
            </td>
        </tr>
    </table>

    <br>
    <br>

    <table class="details-table">
        <tr class="details-head">
            <td>{{trans('applang.item')}}</td>
            <td>{{trans('applang.description')}}</td>
            <td>{{trans('applang.quantity')}}</td>
        </tr>


        <tbody>
        @foreach($salesInvoice->salesInvoiceDetails as $item)
            <tr>
                <td>{{\App\Models\ERP\Inventory\Product::where('id', $item->product_id)->pluck('name')->first()}}</td>
                <td>{{$item->description}}</td>
                <td>{{$item->quantity}}</td>
            </tr>
        @endforeach
        </tbody>

        <tfoot>
        <tr>
            <td colspan="2">{{trans('applang.the_total')}}</td>
            <td>{{$invoice_items_quantities_total}}</td>
        </tr>
        </tfoot>
    </table>

    <br>
    <div class="img-container">
        <img src="{{$barcodePath}}" alt="{{$salesInvoice->inv_number}}" style="width: 100%; height: 50px">
        <div style="font-size: 12px">{{$salesInvoice->inv_number}}</div>
    </div>
</div>

</body>
</html>
