import React from 'react';

/**
 * @typedef {Object} ButtonProps
 * @property {React.ReactNode} children - Button content
 * @property {string} [className=''] - Additional class names
 * @property {'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link'} [variant='default'] - Button style variant
 * @property {'default' | 'sm' | 'lg' | 'icon'} [size='default'] - Button size
 * @property {boolean} [disabled=false] - Whether the button is disabled
 * @property {'button' | 'submit' | 'reset'} [type='button'] - Button HTML type
 * @property {Function} [onClick] - Click handler
 */

/**
 * Button component with different variants and sizes
 * @type {React.ForwardRefExoticComponent<ButtonProps & React.RefAttributes<HTMLButtonElement>>}
 */
const Button = React.forwardRef(({ 
  children,
  className = '',
  variant = 'default',
  size = 'default',
  disabled = false,
  type = 'button',
  ...props
}, ref) => {
  const baseStyles = 'inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none';
  
  const variants = {
    default: 'bg-black text-white hover:bg-gray-800',
    destructive: 'bg-red-500 text-white hover:bg-red-600',
    outline: 'border border-gray-200 bg-white hover:bg-gray-100 hover:text-gray-900',
    secondary: 'bg-gray-100 text-gray-900 hover:bg-gray-200',
    ghost: 'hover:bg-gray-100 hover:text-gray-900',
    link: 'underline-offset-4 hover:underline text-gray-900'
  };

  const sizes = {
    default: 'h-10 px-4 py-2',
    sm: 'h-9 px-3',
    lg: 'h-11 px-8',
    icon: 'h-10 w-10'
  };

  const classes = `${baseStyles} ${variants[variant]} ${sizes[size]} ${className}`;

  return (
    <button
      ref={ref}
      type={type}
      className={classes}
      disabled={disabled}
      {...props}
    >
      {children}
    </button>
  );
});

Button.displayName = 'Button';

export { Button };