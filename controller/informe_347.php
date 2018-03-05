<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014-2016  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_model('cliente.php');
require_model('cuenta.php');
require_model('ejercicio.php');
require_model('factura_cliente.php');
require_model('factura_proveedor.php');
require_model('proveedor.php');

class informe_347 extends fs_controller
{
   public $cantidad;
   public $datos_cli;
   public $datos_pro;
   public $ejercicio;
   public $examinar;
   
   public $sejercicio;
   public $url_descarga;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Modelo 347', 'informes');
   }
   
   protected function private_core()
   {
      $this->cantidad = 3005.06;
      if( isset($_REQUEST['cantidad']) )
      {
         $this->cantidad = floatval($_REQUEST['cantidad']);
      }
      
      $this->ejercicio = new ejercicio();
      $this->sejercicio = $this->ejercicio->get($this->empresa->codejercicio);
      if( isset($_REQUEST['ejercicio']) )
      {
         $this->sejercicio = $this->ejercicio->get($_REQUEST['ejercicio']);
      }
      
      $this->examinar = 'facturacion';
      if( isset($_REQUEST['examinar']) )
      {
         $this->examinar = $_REQUEST['examinar'];
      }
      
      $this->url_descarga = '';
      if($this->sejercicio)
      {
         if($this->examinar == 'contabilidad')
         {
            $this->datos_cli = $this->informe_clientes_contabilidad();
            $this->datos_pro = $this->informe_proveedores_contabilidad();
         }
         else
         {
            $this->datos_cli = $this->informe_clientes();
            $this->datos_pro = $this->informe_proveedores();
         }
         
         if( isset($_GET['ejercicio']) )
         {
            $this->excel();
         }
         else
         {
            $this->url_descarga = $this->url().'&ejercicio='.$this->sejercicio->codejercicio.'&examinar='.$this->examinar.'&cantidad='.$this->cantidad;
         }
      }
      else
      {
         $this->datos_cli = $this->datos_pro = array(
             'filas' => array(),
             'totales' => array(0, 0, 0, 0, 0)
         );
      }
   }
   
   private function informe_clientes()
   {
      $informe = array(
          'filas' => array(),
          'totales' => array(0, 0, 0, 0, 0)
      );
      
      $fila = array(
          'idsubcuenta' => '',
          'cifnif' => '',
          'codcliente' => '',
          'cliente' => '',
          'codpostal' => '',
          'ciudad' => '',
          'provincia' => '',
          't1' => 0,
          't2' => 0,
          't3' => 0,
          't4' => 0,
          'total' => 0
      );
      
      /**
       * Examinamos las facturas de venta y agrupamos la información por cliente y mes
       */
      if( strtolower(FS_DB_TYPE) == 'postgresql')
      {
         $sql = "SELECT codcliente, to_char(fecha,'FMMM') as mes, sum(total) as total
            FROM facturascli WHERE to_char(fecha,'FMYYYY') = ".$this->ejercicio->var2str($this->sejercicio->year())."
            AND irpf = 0
            GROUP BY codcliente,mes ORDER BY codcliente;";
      }
      else
      {
         $sql = "SELECT codcliente, DATE_FORMAT(fecha, '%m') as mes, sum(total) as total
            FROM facturascli WHERE DATE_FORMAT(fecha, '%Y') = ".$this->ejercicio->var2str($this->sejercicio->year())."
            AND irpf = 0
            GROUP BY codcliente,mes ORDER BY codcliente;";
      }
      
      $data = $this->db->select($sql);
      if($data)
      {
         /// rellenamos la tabla con los datos
         foreach($data as $d)
         {
            if($fila['codcliente'] == '')
            {
               $fila['codcliente'] = $d['codcliente'];
            }
            else if($d['codcliente'] != $fila['codcliente'])
            {
               if($fila['total'] > $this->cantidad)
               {
                  $informe['filas'][] = $fila;
               }
               
               $fila['codcliente'] = $d['codcliente'];
               $fila['t1'] = 0;
               $fila['t2'] = 0;
               $fila['t3'] = 0;
               $fila['t4'] = 0;
               $fila['total'] = 0;
            }
            
            if( in_array($d['mes'], array('1', '2','3','01','02','03')) )
            {
               $fila['t1'] += floatval($d['total']);
            }
            else if( in_array($d['mes'], array('4','5','6','04','05','06')) )
            {
               $fila['t2'] += floatval($d['total']);
            }
            else if( in_array($d['mes'], array('7','8','9','07','08','09')) )
            {
               $fila['t3'] += floatval($d['total']);
            }
            else
               $fila['t4'] += floatval($d['total']);
            
            $fila['total'] = $fila['t1'] + $fila['t2'] + $fila['t3'] + $fila['t4'];
         }
         
         /// comprobamos si hay que añadir el último elemento examinado
         if($fila['total'] > $this->cantidad)
         {
            $informe['filas'][] = $fila;
         }
         
         /// rellenamos la información del cliente y los totales
         $cliente = new cliente();
         foreach($informe['filas'] as $i => $value)
         {
            $cli0 = $cliente->get($value['codcliente']);
            if($cli0)
            {
               $informe['filas'][$i]['cliente'] = $cli0;
               $informe['filas'][$i]['cifnif'] = $cli0->cifnif;
               $informe['filas'][$i]['codcliente'] = $cli0->codcliente;
               
               foreach($cli0->get_direcciones() as $dir)
               {
                  if($dir->domfacturacion)
                  {
                     $informe['filas'][$i]['codpostal'] = $dir->codpostal;
                     $informe['filas'][$i]['ciudad'] = $dir->ciudad;
                     $informe['filas'][$i]['provincia'] = $dir->provincia;
                     break;
                  }
               }
            }
            
            $informe['totales'][0] += $value['t1'];
            $informe['totales'][1] += $value['t2'];
            $informe['totales'][2] += $value['t3'];
            $informe['totales'][3] += $value['t4'];
         }
         
         $informe['totales'][4] = $informe['totales'][0] + $informe['totales'][1] + $informe['totales'][2] + $informe['totales'][3];
         
         /// ordenamos por cifnif
         usort($informe['filas'], function($a,$b) {
            return strcasecmp($a['cifnif'], $b['cifnif']);
         });
      }
      
      return $informe;
   }
   
   private function informe_clientes_contabilidad()
   {
      $informe = array(
          'filas' => array(),
          'totales' => array(0, 0, 0, 0, 0)
      );
      
      $fila = array(
          'idsubcuenta' => '',
          'cifnif' => '',
          'codcliente' => '',
          'cliente' => '',
          'codpostal' => '',
          'ciudad' => '',
          'provincia' => '',
          't1' => 0,
          't2' => 0,
          't3' => 0,
          't4' => 0,
          'total' => 0
      );
      
      /**
       * Examinamos las partidas de las subcuentas de clientes y agrupamos la información
       * por subcuenta y mes
       */
      $cuenta0 = new cuenta();
      foreach($cuenta0->all_from_cuentaesp('CLIENT', $this->sejercicio->codejercicio) as $cue)
      {
         if( strtolower(FS_DB_TYPE) == 'postgresql')
         {
            $sql = "select idsubcuenta, to_char(fecha,'FMMM') as mes, sum(debe) as total from co_partidas p, co_asientos a"
                    . " where idsubcuenta IN (select idsubcuenta from co_subcuentas where idcuenta = ".$cue->var2str($cue->idcuenta).")"
                    . " and p.idasiento = a.idasiento"
                    . " and fecha > ".$cue->var2str($this->ejercicio->fechainicio)
                    . " and fecha < ".$cue->var2str($this->ejercicio->fechafin)
                    . " and to_char(fecha,'FMYYYY') = ".$cue->var2str($this->sejercicio->year())
                    . " group by idsubcuenta,mes order by idsubcuenta asc, mes asc;";
         }
         else
         {
            $sql = "select idsubcuenta, DATE_FORMAT(fecha, '%m') as mes, sum(debe) as total from co_partidas p, co_asientos a"
                    . " where idsubcuenta IN (select idsubcuenta from co_subcuentas where idcuenta = ".$cue->var2str($cue->idcuenta).")"
                    . " and p.idasiento = a.idasiento"
                    . " and fecha > ".$cue->var2str($this->ejercicio->fechainicio)
                    . " and fecha < ".$cue->var2str($this->ejercicio->fechafin)
                    . " and DATE_FORMAT(fecha, '%Y') = ".$cue->var2str($this->sejercicio->year())
                    . " group by idsubcuenta,mes order by idsubcuenta asc, mes asc;";
         }
         
         $data = $this->db->select($sql);
         if($data)
         {
            /// rellenamos la tabla con los datos
            foreach($data as $d)
            {
               if($fila['idsubcuenta'] == '')
               {
                  $fila['idsubcuenta'] = $d['idsubcuenta'];
               }
               else if($d['idsubcuenta'] != $fila['idsubcuenta'])
               {
                  if($fila['total'] > $this->cantidad)
                  {
                     $informe['filas'][] = $fila;
                  }
                  
                  $fila['idsubcuenta'] = $d['idsubcuenta'];
                  $fila['t1'] = 0;
                  $fila['t2'] = 0;
                  $fila['t3'] = 0;
                  $fila['t4'] = 0;
                  $fila['total'] = 0;
               }
               
               if( in_array($d['mes'], array('1', '2','3','01','02','03')) )
               {
                  $fila['t1'] += floatval($d['total']);
               }
               else if( in_array($d['mes'], array('4','5','6','04','05','06')) )
               {
                  $fila['t2'] += floatval($d['total']);
               }
               else if( in_array($d['mes'], array('7','8','9','07','08','09')) )
               {
                  $fila['t3'] += floatval($d['total']);
               }
               else
                  $fila['t4'] += floatval($d['total']);
               
               $fila['total'] = $fila['t1'] + $fila['t2'] + $fila['t3'] + $fila['t4'];
            }
            
            /// comprobamos si hay que añadir el último elemento examinado
            if($fila['total'] > $this->cantidad)
            {
               $informe['filas'][] = $fila;
            }
         }
      }
      
      /// completamos la información de los clientes y totales
      foreach($informe['filas'] as $i => $value)
      {
         $cli0 = $this->cliente_from_idsubcuenta($value['idsubcuenta']);
         if($cli0)
         {
            $informe['filas'][$i]['cliente'] = $cli0;
            $informe['filas'][$i]['cifnif'] = $cli0->cifnif;
            $informe['filas'][$i]['codcliente'] = $cli0->codcliente;
            
            foreach($cli0->get_direcciones() as $dir)
            {
               if($dir->domfacturacion)
               {
                  $informe['filas'][$i]['codpostal'] = $dir->codpostal;
                  $informe['filas'][$i]['ciudad'] = $dir->ciudad;
                  $informe['filas'][$i]['provincia'] = $dir->provincia;
                  break;
               }
            }
         }
         
         $informe['totales'][0] += $value['t1'];
         $informe['totales'][1] += $value['t2'];
         $informe['totales'][2] += $value['t3'];
         $informe['totales'][3] += $value['t4'];
      }
      
      $informe['totales'][4] = $informe['totales'][0] + $informe['totales'][1] + $informe['totales'][2] + $informe['totales'][3];
      
      /// ordenamos por cifnif
      usort($informe['filas'], function($a,$b) {
         return strcasecmp($a['cifnif'], $b['cifnif']);
      });
      
      return $informe;
   }
   
   private function cliente_from_idsubcuenta($id)
   {
      $sql = "select * from clientes where codcliente in (select codcliente from co_subcuentascli"
              . " where idsubcuenta = ".$this->ejercicio->var2str($id).")";
      
      $data = $this->db->select($sql);
      if($data)
      {
         return new cliente($data[0]);
      }
      else
         return FALSE;
   }
   
   private function informe_proveedores()
   {
      $informe = array(
          'filas' => array(),
          'totales' => array(0, 0, 0, 0, 0)
      );
      
      $fila = array(
          'idsubcuenta' => '',
          'cifnif' => '',
          'codproveedor' => '',
          'proveedor' => '',
          'codpostal' => '',
          'ciudad' => '',
          'provincia' => '',
          't1' => 0,
          't2' => 0,
          't3' => 0,
          't4' => 0,
          'total' => 0
      );
      
      if( strtolower(FS_DB_TYPE) == 'postgresql')
      {
         $sql = "SELECT codproveedor, to_char(fecha,'FMMM') as mes, sum(total) as total
            FROM facturasprov WHERE to_char(fecha,'FMYYYY') = ".$this->ejercicio->var2str($this->sejercicio->year())."
            AND irpf = 0
            GROUP BY codproveedor, to_char(fecha,'FMMM') ORDER BY codproveedor;";
      }
      else
      {
         $sql = "SELECT codproveedor, DATE_FORMAT(fecha, '%m') as mes, sum(total) as total
            FROM facturasprov WHERE DATE_FORMAT(fecha, '%Y') = ".$this->ejercicio->var2str($this->sejercicio->year())."
            AND irpf = 0
            GROUP BY codproveedor, DATE_FORMAT(fecha, '%m') ORDER BY codproveedor;";
      }
      
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            if($fila['codproveedor'] == '')
            {
               $fila['codproveedor'] = $d['codproveedor'];
            }
            else if($d['codproveedor'] != $fila['codproveedor'])
            {
               if($fila['total'] > $this->cantidad)
               {
                  $informe['filas'][] = $fila;
               }
               
               $fila['codproveedor'] = $d['codproveedor'];
               $fila['t1'] = 0;
               $fila['t2'] = 0;
               $fila['t3'] = 0;
               $fila['t4'] = 0;
               $fila['total'] = 0;
            }
            
            if( in_array($d['mes'], array('1','2','3','01','02','03')) )
            {
               $fila['t1'] += floatval($d['total']);
            }
            else if( in_array($d['mes'], array('4','5','6','04','05','06')) )
            {
               $fila['t2'] += floatval($d['total']);
            }
            else if( in_array($d['mes'], array('7','8','9','07','08','09')) )
            {
               $fila['t3'] += floatval($d['total']);
            }
            else
               $fila['t4'] += floatval($d['total']);
            
            $fila['total'] = $fila['t1'] + $fila['t2'] + $fila['t3'] + $fila['t4'];
         }
         
         if($fila['total'] > $this->cantidad)
         {
            $informe['filas'][] = $fila;
         }
         
         $proveedor = new proveedor();
         foreach($informe['filas'] as $i => $value)
         {
            $pro0 = $proveedor->get($value['codproveedor']);
            if($pro0)
            {
               $informe['filas'][$i]['proveedor'] = $pro0;
               $informe['filas'][$i]['codproveedor'] = $pro0->codproveedor;
               $informe['filas'][$i]['cifnif'] = $pro0->cifnif;
               
               /// obtenemos la dirección del proveedor
               foreach($pro0->get_direcciones() as $dir)
               {
                  if($dir->direccionppal)
                  {
                     $informe['filas'][$i]['codpostal'] = $dir->codpostal;
                     $informe['filas'][$i]['ciudad'] = $dir->ciudad;
                     $informe['filas'][$i]['provincia'] = $dir->provincia;
                     break;
                  }
               }
            }
            
            $informe['totales'][0] += $value['t1'];
            $informe['totales'][1] += $value['t2'];
            $informe['totales'][2] += $value['t3'];
            $informe['totales'][3] += $value['t4'];
         }
         
         $informe['totales'][4] = $informe['totales'][0] + $informe['totales'][1] + $informe['totales'][2] + $informe['totales'][3];
         
         /// ordenamos por cifnif
         usort($informe['filas'], function($a,$b) {
            return strcasecmp($a['cifnif'], $b['cifnif']);
         });
      }
      
      return $informe;
   }
   
   private function informe_proveedores_contabilidad()
   {
      $informe = array(
          'filas' => array(),
          'totales' => array(0, 0, 0, 0, 0)
      );
      
      $fila = array(
          'idsubcuenta' => '',
          'cifnif' => '',
          'codproveedor' => '',
          'proveedor' => '',
          'codpostal' => '',
          'ciudad' => '',
          'provincia' => '',
          't1' => 0,
          't2' => 0,
          't3' => 0,
          't4' => 0,
          'total' => 0
      );
      
      /**
       * Examinamos las partidas de las subcuentas de proveedores y agrupamos la información
       * por subcuenta y mes
       */
      $cuenta0 = new cuenta();
      foreach($cuenta0->all_from_cuentaesp('PROVEE', $this->sejercicio->codejercicio) as $cue)
      {
         if( strtolower(FS_DB_TYPE) == 'postgresql')
         {
            $sql = "select idsubcuenta, to_char(fecha,'FMMM') as mes, sum(debe) as total from co_partidas p, co_asientos a"
                    . " where idsubcuenta IN (select idsubcuenta from co_subcuentas where idcuenta = ".$cue->var2str($cue->idcuenta).")"
                    . " and p.idasiento = a.idasiento"
                    . " and fecha > ".$cue->var2str($this->ejercicio->fechainicio)
                    . " and fecha < ".$cue->var2str($this->ejercicio->fechafin)
                    . " and to_char(fecha,'FMYYYY') = ".$cue->var2str($this->sejercicio->year())
                    . " group by idsubcuenta,mes order by idsubcuenta asc, mes asc;";
         }
         else
         {
            $sql = "select idsubcuenta, DATE_FORMAT(fecha, '%m') as mes, sum(debe) as total from co_partidas p, co_asientos a"
                    . " where idsubcuenta IN (select idsubcuenta from co_subcuentas where idcuenta = ".$cue->var2str($cue->idcuenta).")"
                    . " and p.idasiento = a.idasiento"
                    . " and fecha > ".$cue->var2str($this->ejercicio->fechainicio)
                    . " and fecha < ".$cue->var2str($this->ejercicio->fechafin)
                    . " and DATE_FORMAT(fecha, '%Y') = ".$cue->var2str($this->sejercicio->year())
                    . " group by idsubcuenta,mes order by idsubcuenta asc, mes asc;";
         }
         
         $data = $this->db->select($sql);
         if($data)
         {
            /// rellenamos la tabla con los datos
            foreach($data as $d)
            {
               if($fila['idsubcuenta'] == '')
               {
                  $fila['idsubcuenta'] = $d['idsubcuenta'];
               }
               else if($d['idsubcuenta'] != $fila['idsubcuenta'])
               {
                  if($fila['total'] > $this->cantidad)
                  {
                     $informe['filas'][] = $fila;
                  }
                  
                  $fila['idsubcuenta'] = $d['idsubcuenta'];
                  $fila['t1'] = 0;
                  $fila['t2'] = 0;
                  $fila['t3'] = 0;
                  $fila['t4'] = 0;
                  $fila['total'] = 0;
               }
               
               if( in_array($d['mes'], array('1', '2','3','01','02','03')) )
               {
                  $fila['t1'] += floatval($d['total']);
               }
               else if( in_array($d['mes'], array('4','5','6','04','05','06')) )
               {
                  $fila['t2'] += floatval($d['total']);
               }
               else if( in_array($d['mes'], array('7','8','9','07','08','09')) )
               {
                  $fila['t3'] += floatval($d['total']);
               }
               else
                  $fila['t4'] += floatval($d['total']);
               
               $fila['total'] = $fila['t1'] + $fila['t2'] + $fila['t3'] + $fila['t4'];
            }
            
            /// comprobamos si hay que añadir el último elemento examinado
            if($fila['total'] > $this->cantidad)
            {
               $informe['filas'][] = $fila;
            }
         }
      }
      
      /// completamos la información de los proveedores y totales
      foreach($informe['filas'] as $i => $value)
      {
         $pro0 = $this->proveedor_from_idsubcuenta($value['idsubcuenta']);
         if($pro0)
         {
            $informe['filas'][$i]['proveedor'] = $pro0;
            $informe['filas'][$i]['cifnif'] = $pro0->cifnif;
            $informe['filas'][$i]['codproveedor'] = $pro0->codproveedor;
            
            foreach($pro0->get_direcciones() as $dir)
            {
               if($dir->direccionppal)
               {
                  $informe['filas'][$i]['codpostal'] = $dir->codpostal;
                  $informe['filas'][$i]['ciudad'] = $dir->ciudad;
                  $informe['filas'][$i]['provincia'] = $dir->provincia;
                  break;
               }
            }
         }
         
         $informe['totales'][0] += $value['t1'];
         $informe['totales'][1] += $value['t2'];
         $informe['totales'][2] += $value['t3'];
         $informe['totales'][3] += $value['t4'];
      }
      
      $informe['totales'][4] = $informe['totales'][0] + $informe['totales'][1] + $informe['totales'][2] + $informe['totales'][3];
      
      /// ordenamos por cifnif
      usort($informe['filas'], function($a,$b) {
         return strcasecmp($a['cifnif'], $b['cifnif']);
      });
      
      return $informe;
   }
   
   private function proveedor_from_idsubcuenta($id)
   {
      $sql = "select * from proveedores where codproveedor in (select codproveedor from co_subcuentasprov"
              . " where idsubcuenta = ".$this->ejercicio->var2str($id).")";
      
      $data = $this->db->select($sql);
      if($data)
      {
         return new proveedor($data[0]);
      }
      else
         return FALSE;
   }
   
   private function excel()
   {
      $this->template = FALSE;
      header("Content-Disposition: attachment; filename=\"modelo_347_".$this->sejercicio->year().".xls\"");
      header("Content-Type: application/vnd.ms-excel");
      
      echo "<table>
         <tr>
            <td colspan='10'>Clientes que han comprado mas de ".$this->cantidad." euros en el ejercicio ".$this->sejercicio->nombre.".</td>
         </tr>
         <tr>
            <td>".FS_CIFNIF."</td>
            <td>Cliente</td>
            <td>CP</td>
            <td>Ciudad</td>
            <td>Provincia</td>
            <td>T.1</td>
            <td>T.2</td>
            <td>T.3</td>
            <td>T.4</td>
            <td>Total</td>
         </tr>";
      
      foreach($this->datos_cli['filas'] as $d)
      {
         echo "<tr>
            <td>".$d['cifnif']."</td>
            <td>".$d['cliente']->razonsocial."</td>
            <td>(".$d['codpostal'].")</td>
            <td>".$d['ciudad']."</td>
            <td>".$d['provincia']."</td>
            <td>".number_format($d['t1'], 2, ',', '')."</td>
            <td>".number_format($d['t2'], 2, ',', '')."</td>
            <td>".number_format($d['t3'], 2, ',', '')."</td>
            <td>".number_format($d['t4'], 2, ',', '')."</td>
            <td>".number_format($d['total'], 2, ',', '')."</td>
         </tr>";
      }
      
      echo "<tr>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td>".number_format($this->datos_cli['totales'][0], 2, ',', '')."</td>
            <td>".number_format($this->datos_cli['totales'][1], 2, ',', '')."</td>
            <td>".number_format($this->datos_cli['totales'][2], 2, ',', '')."</td>
            <td>".number_format($this->datos_cli['totales'][3], 2, ',', '')."</td>
            <td>".number_format($this->datos_cli['totales'][4], 2, ',', '')."</td>
         </tr>";
      
      echo "<tr><td></td></tr>
         <tr>
            <td colspan='7'>Proveedores que nos han vendido mas de 3 005.06 euros en el ejercicio ".$this->sejercicio->nombre.".</td>
         </tr>
         <tr>
            <td>".FS_CIFNIF."</td>
            <td>Proveedor</td>
            <td>CP</td>
            <td>Ciudad</td>
            <td>Provincia</td>
            <td>T.1</td>
            <td>T.2</td>
            <td>T.3</td>
            <td>T.4</td>
            <td>Total</td>
         </tr>";
      
      foreach($this->datos_pro['filas'] as $d)
      {
         echo "<tr>
            <td>".$d['cifnif']."</td>
            <td>".$d['proveedor']->razonsocial."</td>
            <td>(".$d['codpostal'].")</td>
            <td>".$d['ciudad']."</td>
            <td>".$d['provincia']."</td>
            <td>".number_format($d['t1'], 2, ',', '')."</td>
            <td>".number_format($d['t2'], 2, ',', '')."</td>
            <td>".number_format($d['t3'], 2, ',', '')."</td>
            <td>".number_format($d['t4'], 2, ',', '')."</td>
            <td>".number_format($d['total'], 2, ',', '')."</td>
         </tr>";
      }
      
      echo "<tr>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td>".number_format($this->datos_pro['totales'][0], 2, ',', '')."</td>
            <td>".number_format($this->datos_pro['totales'][1], 2, ',', '')."</td>
            <td>".number_format($this->datos_pro['totales'][2], 2, ',', '')."</td>
            <td>".number_format($this->datos_pro['totales'][3], 2, ',', '')."</td>
            <td>".number_format($this->datos_pro['totales'][4], 2, ',', '')."</td>
         </tr>";
      
      echo "</table>";
   }
}
