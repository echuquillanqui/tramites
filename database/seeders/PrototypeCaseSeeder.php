<?php

namespace Database\Seeders;

use App\Models\PrototypeCase;
use Illuminate\Database\Seeder;

class PrototypeCaseSeeder extends Seeder
{
    public function run(): void
    {
        $cases = [
            ['slug' => 'posesion', 'title' => 'Solo tengo posesión del terreno', 'summary' => 'Solicitar un certificado municipal de posesión.', 'content' => ['entity' => 'Municipalidad Provincial de Huancayo · Gerencia de Desarrollo Urbano', 'requirements' => ['Solicitud dirigida al alcalde', 'DNI de los posesionarios', 'Plano de ubicación/localización', 'Memoria descriptiva', 'Declaración jurada notarial del solicitante', 'Declaración jurada notarial de 3 vecinos y exhibición de sus DNI', 'Presentar documentos en original'], 'steps' => ['Consultar el caso en la Gerencia de Desarrollo Urbano', 'Reunir los documentos y planos solicitados', 'Verificar firmas profesionales y declaraciones notariales', 'Presentar los documentos originales'], 'cost' => 'S/ 412,60 referencial', 'result' => 'Certificado Municipal de Posesión'], 'sort_order' => 1],
            ['slug' => 'cofopri', 'title' => 'Mi zona está en proceso de formalización', 'summary' => 'Orientación para zonas y programas de formalización de COFOPRI.', 'content' => ['entity' => 'COFOPRI · Oficina Zonal Junín, Jr. Lima 590–594, Huancayo', 'requirements' => ['Recibos de luz o agua', 'Arbitrios o impuesto predial', 'Contrato de compraventa', 'Escritura pública', 'Constancia de posesión', 'Otros documentos que acrediten ocupación'], 'steps' => ['Consultar si la zona forma parte del proceso de formalización', 'Participar del empadronamiento cuando corresponda', 'Esperar la revisión de la información', 'Recibir el título si la zona cumple el proceso'], 'cost' => 'Gratuito dentro del proceso COFOPRI', 'note' => 'Si no tienes documentos, podrían considerarse declaraciones de vecinos.'], 'sort_order' => 2],
            ['slug' => 'sunarp', 'title' => 'Tengo documentos, pero no está inscrita', 'summary' => 'Inmatriculación del predio en SUNARP.', 'content' => ['entity' => 'SUNARP · Oficina Registral Huancayo', 'requirements' => ['Escritura pública u otro título de propiedad', 'Planos según el caso', 'En zonas no catastradas, planos firmados por verificador competente y visados por la municipalidad'], 'steps' => ['Revisar que el título y los planos correspondan al predio', 'Consultar requisitos para la inmatriculación en SUNARP', 'Presentar el parte notarial o título según corresponda'], 'cost' => 'S/ 83,00 referencial', 'note' => 'Si presentas parte notarial, normalmente lo presenta la notaría o su dependiente acreditado.'], 'sort_order' => 3],
            ['slug' => 'herencia', 'title' => 'Herencia o reparto familiar', 'summary' => 'Regularización o división de una propiedad familiar.', 'content' => ['entity' => 'Notaría, SUNARP y SATH / Municipalidad, según corresponda', 'requirements' => ['Partida de defunción', 'Declaratoria de herederos', 'Escritura pública o sentencia de división y partición'], 'steps' => ['Identificar el documento sucesorio disponible', 'Consultar con notaría o SUNARP la ruta aplicable', 'Inscribir la transferencia o división', 'Actualizar la información tributaria si aplica'], 'note' => 'No todo se resuelve en la Municipalidad.'], 'sort_order' => 4],
        ];

        foreach ($cases as $case) {
            PrototypeCase::updateOrCreate(['slug' => $case['slug']], $case);
        }
    }
}
