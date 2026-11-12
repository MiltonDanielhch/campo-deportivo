import Hero from '@/components/landing/Hero';
import ComoFunciona from '@/components/landing/ComoFunciona';
import CamposDestacados from '@/components/landing/CamposDestacados';
import BandaPiscina from '@/components/landing/BandaPiscina';
import Faq from '@/components/landing/Faq';
import BandaCTA from '@/components/landing/BandaCTA';
import InfoInstitucional from '@/components/landing/InfoInstitucional';
import Footer from '@/components/landing/Footer';

export default function Landing() {
  return (
    <>
      <Hero />
      <ComoFunciona />
      <CamposDestacados />
      <BandaPiscina />
      <Faq />
      <BandaCTA />
      <InfoInstitucional />
      {/* <Footer /> */}
    </>
  );
}