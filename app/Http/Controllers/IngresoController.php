<?php

namespace sisVentas\Http\Controllers;

use Illuminate\Http\Request;

use sisVentas\Http\Requests;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Input;
use sisVentas\Http\Requests\IngresoFormRequest;
use sisVentas\Ingreso;
use sisVentas\DetalleIngreso;
use DB;

//para la fecha y la hora
use Carbon\Carbon;
use Response;
use Illuminate\Support\Collection;

class IngresoController extends Controller
{
    public function __construct()
    {
//para que se deba iniciar sesion 
        $this->middleware('auth');
    }
    public function index(Request $request)
    {
        if ($request)
        {
          
            $query=trim($request->get('searchText'));
            $ingresos=DB::table('ingreso as i')
            ->join('persona as p', 'i.idpersona','=','p.idpersona')
            ->join('detalle_ingreso as di', 'i.idingreso','=','di.idingreso')
            ->select('i.idingreso','i.fecha_hora','p.nombre','i.tipo_comprobante','i.serie_comprobante','i.num_comprobante','i.impuesto','i.estado',DB::raw('sum(di.cantidad*di.precio_compra) as total'))
            ->where('i.num_comprobante','LIKE','%'.$query.'%')
            ->orderBy('i.idingreso','desc')
            ->groupBy('i.idingreso','i.fecha_hora','p.nombre','i.tipo_comprobante','i.serie_comprobante','i.num_comprobante','i.impuesto','i.estado')
            ->paginate(7);
            return view('compras.ingreso.index',["ingresos"=>$ingresos,"searchText"=>$query]);
        }
    }
    public function create()
    {
        //para crear una lista de proveedores de la cual se pueda elegir
        $personas=DB::table('persona')->where('tipo_persona','=','Proveedor')->get();
        //para crear una lista de articulos que se pueden traer
        $articulos = DB::table('articulo as art')
            ->select(DB::raw('CONCAT(art.codigo, " ",art.nombre) AS articulo'),'art.idarticulo')
            ->where('art.estado','=','Activo')
            ->get();
        return view("compras.ingreso.create",["personas"=>$personas,"articulos"=>$articulos]);
    }
    //funcion para almacenar
    public function store (IngresoFormRequest $request)
    {
        try{
            DB::beginTransaction();
               $ingreso=new Ingreso;
               $ingreso->idpersona=$request->get('idpersona');
               $ingreso->tipo_comprobante=$request->get('tipo_comprobante');
               $ingreso->serie_comprobante=$request->get('serie_comprobante');
               $ingreso->num_comprobante=$request->get('num_comprobante');
               $mytime = Carbon::now('America/Argentina/Buenos_Aires');
               $ingreso->fecha_hora=$mytime->toDateTimeString();
               $ingreso->impuesto='21';
               $ingreso->estado='A';
               $ingreso->save();
             
               //va a ser un array 
               $idarticulo = $request->get('idarticulo');
               $cantidad = $request->get('cantidad');
               $precio_compra = $request->get('precio_compra');
               $precio_venta = $request->get('precio_venta');

               //esto viene desde la vista, recorre el array de detalles
               $cont = 0;

               while($cont < count($idarticulo)){
                    $detalle = new DetalleIngreso();
                    //el idingreso es el generado en la consulta cuando se graba el ingreso
                    $detalle->idingreso= $ingreso->idingreso;
                    $detalle->idarticulo=$idarticulo[$cont];
                    $detalle->cantidad= $cantidad[$cont];
                    $detalle->precio_compra= $precio_compra[$cont];
                    $detalle->precio_venta= $precio_venta[$cont];
                    $detalle->save();

                   $cont=$cont+1;
               }

            DB::commit();
        }catch(\Exception $e)
        {
            DB::rollback();
        }

        return Redirect::to('compras/ingreso');
    }

    public function show($id)
    {
 $ingreso=DB::table('ingreso as i') 
     ->join('persona as p','i.idpersona','=','p.idpersona') 
     ->join('detalle_ingreso as di','i.idingreso','=','di.idingreso') 
     ->select('i.idingreso','i.fecha_hora','p.nombre','i.tipo_comprobante','i.serie_comprobante',
              'i.num_comprobante','i.impuesto','i.estado',
              DB::raw('sum(di.cantidad*precio_compra) as total')) 
     ->where('i.idingreso','=',$id) 
     ->first(); 
        $detalles=DB::table('detalle_ingreso as d') 
            ->join('articulo as a','d.idarticulo','=','a.idarticulo') 
            ->select('a.nombre as articulo','d.cantidad','d.precio_compra','d.precio_venta') 
            ->where('d.idingreso','=',$id) ->get(); 
        return view("compras.ingreso.show",["ingreso"=>$ingreso,"detalles"=>$detalles]);
 
    }

    //en caso de que se cancelen los ingresos
    public function destroy($id)
    {
        $ingreso->Ingreso::findOrFail($id);
        $ingreso->Estado='C';
        $ingreso->update();
        return Redirect::to('compras/ingreso');
    }
}
