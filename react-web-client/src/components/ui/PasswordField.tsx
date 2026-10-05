import { useState, type ComponentProps } from 'react'
import { EyeIcon, EyeSlashIcon } from './icons'
import { TextField } from './TextField'

type PasswordFieldProps = Omit<ComponentProps<typeof TextField>, 'type' | 'trailing'>

export function PasswordField(props: PasswordFieldProps) {
    const [visible, setVisible] = useState(false)

    return (
        <TextField
            {...props}
            type={visible ? 'text' : 'password'}
            trailing={
                <button
                    type="button"
                    onClick={() => setVisible((current) => !current)}
                    aria-label={visible ? 'Hide password' : 'Show password'}
                    aria-pressed={visible}
                    className="ml-2 rounded-full p-1"
                >
                    {visible ? <EyeSlashIcon /> : <EyeIcon />}
                </button>
            }
        />
    )
}