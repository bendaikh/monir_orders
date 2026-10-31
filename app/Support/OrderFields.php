<?php

namespace App\Support;

class OrderFields
{
    /**
     * @return array<string, array{label: string, required: bool}>
     */
    public static function definitions(): array
    {
        return [
            'reference' => ['label' => 'Référence', 'required' => true],
            'customer_name' => ['label' => 'Nom du client', 'required' => true],
            'phone' => ['label' => 'Téléphone', 'required' => false],
            'city' => ['label' => 'Ville', 'required' => false],
            'address' => ['label' => 'Adresse', 'required' => false],
            'total_amount' => ['label' => 'Montant total', 'required' => false],
            'ordered_at' => ['label' => 'Date de commande', 'required' => false],
            'product' => ['label' => 'Article', 'required' => false],
            'quantity' => ['label' => 'Quantité', 'required' => false],
            'unit_price' => ['label' => 'Prix unitaire', 'required' => false],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function defaultSource(): array
    {
        return [
            'reference' => 'Référence',
            'customer_name' => 'Nom du client',
            'phone' => 'Téléphone',
            'city' => 'Ville',
            'address' => 'Adresse',
            'total_amount' => 'Montant total',
            'ordered_at' => 'Date de commande',
            'product' => 'Article',
            'quantity' => 'Quantité',
            'unit_price' => 'Prix unitaire',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function defaultDestination(): array
    {
        return [
            'reference' => 'Order ID',
            'customer_name' => 'Customer Name',
            'phone' => 'Phone',
            'city' => 'City',
            'address' => 'Address',
            'total_amount' => 'Total',
            'ordered_at' => 'Order Date',
            'product' => 'Product',
            'quantity' => 'Quantity',
            'unit_price' => 'Unit Price',
        ];
    }
}
