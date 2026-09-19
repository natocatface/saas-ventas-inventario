<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Marca;
use App\Models\Plan;
use App\Models\PlataformaConfig;
use App\Models\Suscripcion;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Support\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ===== Configuración global de la plataforma =====
        PlataformaConfig::create([
            'nombre_saas' => 'SaaS Ventas e Inventario',
            'dias_trial' => 14,
            'correo_soporte' => 'soporte@saas.test',
            'moneda' => 'S/',
        ]);

        // ===== Planes (catálogo de suscripción) =====
        $planGratis = Plan::create([
            'nombre' => 'Gratis', 'slug' => 'gratis', 'precio' => 0,
            'limite_productos' => 20, 'limite_usuarios' => 1, 'limite_ventas_mes' => 100,
            'descripcion' => 'Para empezar y probar el sistema.', 'orden' => 1,
        ]);
        $planEmprendedor = Plan::create([
            'nombre' => 'Emprendedor', 'slug' => 'emprendedor', 'precio' => 49.00,
            'limite_productos' => 500, 'limite_usuarios' => 5, 'limite_ventas_mes' => 3000,
            'descripcion' => 'Para negocios en crecimiento.', 'orden' => 2,
        ]);
        $planNegocio = Plan::create([
            'nombre' => 'Negocio', 'slug' => 'negocio', 'precio' => 99.00,
            'limite_productos' => null, 'limite_usuarios' => null, 'limite_ventas_mes' => null,
            'descripcion' => 'Todo ilimitado para operaciones grandes.', 'orden' => 3,
        ]);

        // ===== Super administrador de la plataforma (sin empresa) =====
        User::create([
            'empresa_id' => null,
            'name' => 'Super Admin',
            'email' => 'super@saas.test',
            'password' => 'password',
            'rol' => 'admin',
            'is_super' => true,
            'activo' => true,
        ]);

        // ===== Empresa / Tenant #1 =====
        $empresa = Empresa::create([
            'nombre' => 'Mi Negocio Demo',
            'ruc' => '20123456789',
            'direccion' => 'Av. Principal 123, Lima',
            'telefono' => '01-4567890',
            'email' => 'ventas@minegocio.test',
            'moneda' => 'S/',
            'igv' => 18.00,
            'plan_id' => $planNegocio->id,
            'estado_suscripcion' => 'activa',
            'suscripcion_termina_en' => Carbon::today()->addMonth(),
        ]);

        Suscripcion::create([
            'empresa_id' => $empresa->id, 'plan_id' => $planNegocio->id,
            'estado' => 'activa', 'monto' => $planNegocio->precio,
            'inicia_en' => Carbon::today(), 'termina_en' => Carbon::today()->addMonth(),
        ]);

        // A partir de aquí todo lo creado pertenece a esta empresa (tenant).
        Tenant::set($empresa->id);

        // ===== Usuarios =====
        $admin = User::create([
            'name' => 'Administrador',
            'email' => 'admin@saas.test',
            'password' => 'password',
            'rol' => 'admin',
            'activo' => true,
        ]);

        User::create([
            'name' => 'Vendedor Demo',
            'email' => 'vendedor@saas.test',
            'password' => 'password',
            'rol' => 'vendedor',
            'activo' => true,
        ]);

        // ===== Categorías =====
        $categorias = collect(['Abarrotes', 'Bebidas', 'Limpieza', 'Snacks', 'Lácteos'])
            ->map(fn ($n) => Categoria::create(['nombre' => $n]));

        // ===== Marcas =====
        $marcas = collect(['Genérico', 'Gloria', 'Coca-Cola', 'Nestlé', 'Sapolio'])
            ->map(fn ($n) => Marca::create(['nombre' => $n]));

        // ===== Proveedores =====
        Proveedor::create(['nombre' => 'Distribuidora Central SAC', 'ruc' => '20123456789', 'telefono' => '01-4567890']);
        Proveedor::create(['nombre' => 'Mayorista El Sol EIRL', 'ruc' => '20987654321', 'telefono' => '01-3216540']);

        // ===== Productos =====
        $nombres = [
            ['Arroz Costeño 5kg', 18.50, 22.00, 'Abarrotes'],
            ['Aceite Primor 1L', 7.20, 9.50, 'Abarrotes'],
            ['Azúcar Rubia 1kg', 3.10, 4.50, 'Abarrotes'],
            ['Coca-Cola 500ml', 1.80, 3.00, 'Bebidas'],
            ['Agua San Luis 625ml', 0.90, 1.50, 'Bebidas'],
            ['Inca Kola 1.5L', 3.50, 5.50, 'Bebidas'],
            ['Detergente Sapolio 900g', 6.00, 8.90, 'Limpieza'],
            ['Lejía Clorox 1L', 2.80, 4.20, 'Limpieza'],
            ['Papel Higiénico x4', 3.20, 5.00, 'Limpieza'],
            ['Galletas Oreo', 1.20, 2.00, 'Snacks'],
            ['Papitas Lays 145g', 3.00, 4.80, 'Snacks'],
            ['Chocolate Sublime', 1.00, 1.80, 'Snacks'],
            ['Leche Gloria Tarro', 3.30, 4.90, 'Lácteos'],
            ['Yogurt Gloria 1L', 4.50, 6.50, 'Lácteos'],
            ['Queso Fresco 500g', 9.00, 13.00, 'Lácteos'],
        ];

        $productos = collect();
        foreach ($nombres as $i => [$nombre, $compra, $venta, $cat]) {
            $productos->push(Producto::create([
                'codigo' => 'P' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'nombre' => $nombre,
                'categoria_id' => $categorias->firstWhere('nombre', $cat)->id,
                'marca_id' => $marcas->random()->id,
                'precio_compra' => $compra,
                'precio_venta' => $venta,
                'stock' => rand(3, 80),
                'stock_minimo' => 10,
            ]));
        }

        // ===== Clientes =====
        $clientes = collect([
            ['Juan Pérez', 'DNI', '45678912'],
            ['María López', 'DNI', '10293847'],
            ['Comercial Andina SAC', 'RUC', '20456789123'],
            ['Carlos Ramírez', 'DNI', '73829104'],
            ['Bodega Doña Rosa', 'RUC', '20567891234'],
        ])->map(fn ($c) => Cliente::create([
            'nombre' => $c[0], 'tipo_documento' => $c[1], 'numero_documento' => $c[2],
        ]));

        // ===== Ventas demo (últimos 30 días) para poblar el dashboard =====
        $contador = 1;
        for ($d = 29; $d >= 0; $d--) {
            $fecha = Carbon::today()->subDays($d);
            $numVentas = rand(1, 6);

            for ($v = 0; $v < $numVentas; $v++) {
                $venta = Venta::create([
                    'numero' => 'V-' . str_pad($contador++, 6, '0', STR_PAD_LEFT),
                    'cliente_id' => $clientes->random()->id,
                    'user_id' => $admin->id,
                    'tipo_comprobante' => collect(['TICKET', 'BOLETA', 'FACTURA'])->random(),
                    'metodo_pago' => collect(['EFECTIVO', 'TARJETA', 'YAPE', 'TRANSFERENCIA'])->random(),
                    'estado' => 'COMPLETADA',
                    'created_at' => $fecha->copy()->addHours(rand(8, 20))->addMinutes(rand(0, 59)),
                    'updated_at' => $fecha,
                ]);

                $subtotal = 0;
                $items = $productos->random(rand(1, 4));
                foreach ($items as $prod) {
                    $cant = rand(1, 5);
                    $sub = $cant * $prod->precio_venta;
                    $subtotal += $sub;

                    VentaDetalle::create([
                        'venta_id' => $venta->id,
                        'producto_id' => $prod->id,
                        'descripcion' => $prod->nombre,
                        'cantidad' => $cant,
                        'precio' => $prod->precio_venta,
                        'subtotal' => $sub,
                    ]);
                }

                $impuesto = round($subtotal * 0.18, 2);
                $venta->update([
                    'subtotal' => $subtotal,
                    'impuesto' => $impuesto,
                    'total' => $subtotal + $impuesto,
                ]);
            }
        }

        // ===================================================================
        // ===== Empresa / Tenant #2 (para demostrar el aislamiento) =========
        // ===================================================================
        $empresa2 = Empresa::create([
            'nombre' => 'Boutique Luna',
            'ruc' => '20999888777',
            'direccion' => 'Jr. Comercio 456, Arequipa',
            'moneda' => 'S/',
            'igv' => 18.00,
            'plan_id' => $planEmprendedor->id,
            'estado_suscripcion' => 'trial',
            'trial_termina_en' => Carbon::today()->addDays(14),
        ]);

        Suscripcion::create([
            'empresa_id' => $empresa2->id, 'plan_id' => $planEmprendedor->id,
            'estado' => 'trial', 'monto' => 0,
            'inicia_en' => Carbon::today(), 'termina_en' => Carbon::today()->addDays(14),
        ]);

        Tenant::set($empresa2->id);

        User::create([
            'name' => 'Dueña Boutique',
            'email' => 'admin@boutique.test',
            'password' => 'password',
            'rol' => 'admin',
            'activo' => true,
        ]);

        $catRopa = Categoria::create(['nombre' => 'Ropa']);
        Categoria::create(['nombre' => 'Accesorios']);

        foreach ([
            ['B0001', 'Blusa de lino', 25.00, 49.90],
            ['B0002', 'Pantalón jean', 40.00, 89.90],
            ['B0003', 'Cartera de cuero', 60.00, 129.90],
        ] as [$cod, $nom, $c, $v]) {
            Producto::create([
                'codigo' => $cod,
                'nombre' => $nom,
                'categoria_id' => $catRopa->id,
                'precio_compra' => $c,
                'precio_venta' => $v,
                'stock' => rand(5, 30),
                'stock_minimo' => 5,
            ]);
        }

        Cliente::create(['nombre' => 'Ana Torres', 'tipo_documento' => 'DNI', 'numero_documento' => '40506070']);

// Limpia el tenant al finalizar el seeder.
        Tenant::clear();
    }
}
