import { QRCodeSVG } from 'qrcode.react';
import { Button } from '@/components/ui/button';
import { ExternalLink } from 'lucide-react';

interface DatosCobro {
  qr_string?: string | null;
  qr_image_base64?: string | null;
  checkout_url?: string | null;
}

interface MedioDePagoProps {
  datos: DatosCobro | null;
}

export default function MedioDePago({ datos }: MedioDePagoProps) {
  if (!datos) {
    return (
      <p className="text-center text-gray-600 py-8">
        El sistema de cobro no devolvió un medio de pago. Recargá la página o
        contactá a soporte.
      </p>
    );
  }

  // Prioridad 1: QR string (lo genera el navegador, liviano)
  if (datos.qr_string) {
    return (
      <div className="flex flex-col items-center gap-4">
        <div className="bg-white p-4 border rounded-lg">
          <QRCodeSVG value={datos.qr_string} size={220} />
        </div>
        <p className="text-sm text-gray-600 text-center">
          Escaneá el QR con tu app de banca móvil
        </p>
      </div>
    );
  }

  // Prioridad 2: imagen base64 del Core
  if (datos.qr_image_base64) {
    return (
      <div className="flex flex-col items-center gap-4">
        <img
          src={`data:image/png;base64,${datos.qr_image_base64}`}
          alt="QR de pago"
          className="w-[220px] h-[220px] border rounded-lg bg-white p-2"
        />
        <p className="text-sm text-gray-600 text-center">
          Escaneá el QR con tu app de banca móvil
        </p>
      </div>
    );
  }

  // Prioridad 3: URL de checkout
  if (datos.checkout_url) {
    return (
      <div className="flex flex-col items-center gap-4">
        <Button
          size="lg"
          onClick={() => window.open(datos.checkout_url!, '_blank')}
        >
          <ExternalLink className="w-4 h-4 mr-2" />
          Pagar en el navegador
        </Button>
        <p className="text-sm text-gray-600 text-center">
          Se abrirá el checkout del Core en una pestaña nueva
        </p>
      </div>
    );
  }

  return (
    <p className="text-center text-gray-600 py-8">
      El sistema de cobro no devolvió un medio de pago válido.
    </p>
  );
}