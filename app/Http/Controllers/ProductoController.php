<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Empresa;
use App\Models\Marca;
use App\Models\Producto;
use App\Support\ExportsCsv;
use Illuminate\Http\Request;
use App\Support\Tenant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    use ExportsCsv;

    public function index(Request $request)
    {
        $q = $request->get('q');
        $categoriaId = $request->get('categoria');
        $estado = $request->get('estado'); // '', 'bajo'

        $productos = Producto::with(['categoria', 'marca'])
            ->when($q, fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('nombre', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%");
            }))
            ->when($categoriaId, fn ($query) => $query->where('categoria_id', $categoriaId))
            ->when($estado === 'bajo', fn ($query) => $query->stockBajo())
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        $categorias = Categoria::orderBy('nombre')->get();

        return view('productos.index', compact('productos', 'categorias', 'q', 'categoriaId', 'estado'));
    }

    public function export(Request $request)
    {
        $q = $request->get('q');
        $categoriaId = $request->get('categoria');
        $estado = $request->get('estado');

        $filas = Producto::with(['categoria', 'marca'])
            ->when($q, fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('nombre', 'like', "%{$q}%")->orWhere('codigo', 'like', "%{$q}%");
            }))
            ->when($categoriaId, fn ($query) => $query->where('categoria_id', $categoriaId))
            ->when($estado === 'bajo', fn ($query) => $query->stockBajo())
            ->orderBy('nombre')->get()
            ->map(fn ($p) => [
                $p->codigo, $p->nombre,
                $p->categoria->nombre ?? '', $p->marca->nombre ?? '', $p->unidad,
                number_format($p->precio_compra, 2, '.', ''),
                number_format($p->precio_venta, 2, '.', ''),
                $p->stock, $p->stock_minimo,
                $p->activo ? 'Activo' : 'Inactivo',
            ]);

        return $this->descargarCsv('productos',
            ['Código', 'Nombre', 'Categoría', 'Marca', 'Unidad', 'Precio compra', 'Precio venta', 'Stock', 'Stock mínimo', 'Estado'],
            $filas);
    }

    public function create()
    {
        return view('productos.create', [
            'producto' => new Producto(['activo' => true, 'stock_minimo' => 5, 'unidad' => 'UND']),
            'categorias' => Categoria::where('activo', true)->orderBy('nombre')->get(),
            'marcas' => Marca::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request)
    {
        if (Empresa::actual()->limiteAlcanzado('productos')) {
            return back()->withInput()
                ->with('error', 'Alcanzaste el límite de productos de tu plan. Mejora tu plan para registrar más.');
        }

        $data = $this->validar($request);
        $data['imagen'] = $this->guardarImagen($request);

        Producto::create($data);

        return redirect()->route('productos.index')
            ->with('success', 'Producto registrado correctamente.');
    }

    public function edit(Producto $producto)
    {
        return view('productos.edit', [
            'producto' => $producto,
            'categorias' => Categoria::where('activo', true)->orderBy('nombre')->get(),
            'marcas' => Marca::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Producto $producto)
    {
        $data = $this->validar($request, $producto->id);

        if ($request->hasFile('imagen')) {
            if ($producto->imagen) {
                Storage::disk('public')->delete($producto->imagen);
            }
            $data['imagen'] = $this->guardarImagen($request);
        }

        $producto->update($data);

        return redirect()->route('productos.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Producto $producto)
    {
        if ($producto->imagen) {
            Storage::disk('public')->delete($producto->imagen);
        }

        $producto->delete();

        return back()->with('success', 'Producto eliminado.');
    }

    private function validar(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'codigo' => [
                'required', 'string', 'max:50',
                // Único por empresa (tenant), no de forma global.
                Rule::unique('productos', 'codigo')
                    ->where(fn ($q) => $q->where('empresa_id', Tenant::id()))
                    ->ignore($id),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'categoria_id' => ['nullable', 'exists:categorias,id'],
            'marca_id' => ['nullable', 'exists:marcas,id'],
            'unidad' => ['required', 'string', 'max:20'],
            'precio_compra' => ['required', 'numeric', 'min:0'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'stock_minimo' => ['required', 'integer', 'min:0'],
            'imagen' => ['nullable', 'image', 'max:2048'],
        ], [
            'codigo.required' => 'El código es obligatorio.',
            'codigo.unique' => 'Ya existe un producto con ese código.',
            'nombre.required' => 'El nombre es obligatorio.',
            'precio_venta.required' => 'El precio de venta es obligatorio.',
            'imagen.image' => 'El archivo debe ser una imagen.',
            'imagen.max' => 'La imagen no debe superar 2 MB.',
        ]);

        $data['activo'] = $request->boolean('activo');
        unset($data['imagen']); // se maneja aparte

        return $data;
    }

    private function guardarImagen(Request $request): ?string
    {
        if ($request->hasFile('imagen')) {
            return $request->file('imagen')->store('productos', 'public');
        }
        return null;
    }
}
