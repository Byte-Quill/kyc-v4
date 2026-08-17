import { createContext, useContext, useState } from 'react'

const FlashContext = createContext(null)

export function FlashProvider({ children }) {
  const [items, setItems] = useState([])

  const push = (type, message) => {
    const id = Date.now() + Math.random()
    setItems((prev) => [...prev, { id, type, message }])
    setTimeout(() => {
      setItems((prev) => prev.filter((f) => f.id !== id))
    }, 6000)
  }

  return <FlashContext.Provider value={{ items, push }}>{children}</FlashContext.Provider>
}

export function useFlash() {
  return useContext(FlashContext)
}

/** Renders the active flash messages. */
export default function Flash() {
  const { items } = useFlash()
  if (!items.length) return null
  return (
    <div className="flashes">
      {items.map((f) => (
        <div key={f.id} className={`flash flash-${f.type}`}>
          {f.message}
        </div>
      ))}
    </div>
  )
}
