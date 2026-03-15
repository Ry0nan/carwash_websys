<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        // Motor services use a single flat price (no vehicle size).
        $motorServices = [
            ['service_name' => 'Motor Wash',         'service_group' => 'MOTOR_MAIN'],
            ['service_name' => 'Motor Wash with Wax', 'service_group' => 'MOTOR_MAIN'],
        ];

        $motorPrices = [
            'Motor Wash'          => 100.00,
            'Motor Wash with Wax' => 120.00,
        ];

        foreach ($motorServices as $svc) {
            $service = Service::query()->firstOrCreate(
                ['service_name' => $svc['service_name']],
                [
                    'vehicle_category' => 'MOTOR',
                    'service_group'    => $svc['service_group'],
                    'is_active'        => 1,
                ]
            );

            $service->forceFill([
                'vehicle_category' => 'MOTOR',
                'service_group'    => $svc['service_group'],
                'is_active'        => 1,
            ])->save();

            $serviceId = $service->service_id;

            // Fixed price — vehicle_size is NULL for motor
            DB::table('service_pricing')->updateOrInsert(
                ['service_id' => $serviceId, 'vehicle_size' => null],
                ['price' => $motorPrices[$svc['service_name']], 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // Car services are priced by vehicle size.
        $carServices = [
            ['service_name' => 'Package 1',   'service_group' => 'PACKAGE'],
            ['service_name' => 'Package 2',   'service_group' => 'PACKAGE'],
            ['service_name' => 'Package 3',   'service_group' => 'PACKAGE'],
            ['service_name' => 'Underwash',   'service_group' => 'ADDON'],
            ['service_name' => 'Engine Wash', 'service_group' => 'ADDON'],
            ['service_name' => 'Complete',    'service_group' => 'BUNDLE'],
        ];

        // Pricing table: [service_name => [SMALL, MEDIUM, LARGE, XL]]
        $carPrices = [
            'Package 1'   => ['SMALL' => 180, 'MEDIUM' => 200, 'LARGE' => 220, 'XL' => 250],
            'Package 2'   => ['SMALL' => 220, 'MEDIUM' => 250, 'LARGE' => 280, 'XL' => 310],
            'Package 3'   => ['SMALL' => 260, 'MEDIUM' => 300, 'LARGE' => 330, 'XL' => 360],
            'Underwash'   => ['SMALL' => 150, 'MEDIUM' => 200, 'LARGE' => 250, 'XL' => 300],
            'Engine Wash' => ['SMALL' => 200, 'MEDIUM' => 250, 'LARGE' => 300, 'XL' => 350],
            'Complete'    => ['SMALL' => 650, 'MEDIUM' => 750, 'LARGE' => 900, 'XL' => 1000],
        ];

        $sizes = ['SMALL', 'MEDIUM', 'LARGE', 'XL'];

        foreach ($carServices as $svc) {
            $service = Service::query()->firstOrCreate(
                ['service_name' => $svc['service_name']],
                [
                    'vehicle_category' => 'CAR',
                    'service_group'    => $svc['service_group'],
                    'is_active'        => 1,
                ]
            );

            $service->forceFill([
                'vehicle_category' => 'CAR',
                'service_group'    => $svc['service_group'],
                'is_active'        => 1,
            ])->save();

            $serviceId = $service->service_id;

            foreach ($sizes as $size) {
                DB::table('service_pricing')->updateOrInsert(
                    ['service_id' => $serviceId, 'vehicle_size' => $size],
                    ['price' => $carPrices[$svc['service_name']][$size], 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }
    }
}
