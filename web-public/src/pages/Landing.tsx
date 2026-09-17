import { Helmet } from 'react-helmet-async';
import Hero from '@/components/landing/Hero';
import ComoFunciona from '@/components/landing/ComoFunciona';
import CamposDestacados from '@/components/landing/CamposDestacados';
import InfoInstitucional from '@/components/landing/InfoInstitucional';
import Footer from '@/components/landing/Footer';

export default function Landing() {
  return (
    <>
      <Helmet>
        <title>Reserva de Canchas Deportivas - GAD Beni</title>
        <meta
          name="description"
          content="Reserva canchas de fútbol, tenis y más en Trinidad, Beni. Pago online, confirmación inmediata."
        />
        <meta property="og:title" content="Reserva de Canchas Deportivas - GAD Beni" />
        <meta
          property="og:description"
          content="Reserva canchas de fútbol, tenis y más en Trinidad, Beni. Pago online, confirmación inmediata."
        />
        <meta property="og:type" content="website" />
        <meta property="og:url" content="https://canchas.gadbeni.bo" />
      </Helmet>

      <div className="min-h-screen flex flex-col">
        <Hero />
        <ComoFunciona />
        <CamposDestacados />
        <InfoInstitucional />
        <Footer />
      </div>
    </>
  );
}
