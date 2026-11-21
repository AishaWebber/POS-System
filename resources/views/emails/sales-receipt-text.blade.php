POS-System
Sales receipt #{{ str_pad($sale->id,5,'0',STR_PAD_LEFT) }}

Customer: {{ $sale->customer_name }}
Date: {{ $sale->created_at->format('M d, Y H:i') }}
Staff: {{ $sale->staff_name }}
Product: {{ $sale->product_name }}
Quantity: {{ $sale->quantity }}
Total: {{ peso($sale->total_price) }}

Thank you for your purchase.
