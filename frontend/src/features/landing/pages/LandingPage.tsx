import { FAQ } from '../components/FAQ'
import { Header } from '../components/Header'
import { Hero } from '../components/Hero'
import { HowItWorks } from '../components/HowItWorks'
import { Privacy } from '../components/Privacy'
import { SupportedFiles } from '../components/SupportedFiles'
import { TargetUsers } from '../components/TargetUsers'

// Puts the landing page sections together, top to bottom.
export function LandingPage() {
  return (
    <>
      <Header />
      <main>
        <Hero />
        <TargetUsers />
        <SupportedFiles />
        <HowItWorks />
        <Privacy />
        <FAQ />
      </main>
    </>
  )
}
