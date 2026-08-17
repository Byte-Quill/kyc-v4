import { useState } from 'react'

/** A button that asks for confirmation before running its action. */
export default function ConfirmButton({ message, children, className = '', onClick, ...rest }) {
  const [confirming, setConfirming] = useState(false)

  const handleClick = (e) => {
    if (!confirming) {
      e.preventDefault()
      setConfirming(true)
      return
    }
    setConfirming(false)
    if (onClick) onClick(e)
  }

  return (
    <button
      {...rest}
      className={className}
      onClick={handleClick}
      onBlur={() => setConfirming(false)}
    >
      {confirming ? 'Are you sure? Click again to confirm' : children}
    </button>
  )
}
