import { Header } from '../components/Header'
import { Hero } from '../components/Hero'

// Puts the landing page sections together, top to bottom.
export function LandingPage() {
  return (
    <>
      <Header />
      <main>
        <Hero />
      </main>
    </>
  )
}
