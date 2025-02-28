import React from 'react';

export const Alert = ({ children, variant = 'default', className = '' }) => {
  const baseStyles = 'p-4 rounded-lg border';
  const variants = {
    default: 'bg-gray-100 border-gray-200 text-gray-800',
    destructive: 'bg-red-100 border-red-200 text-red-800',
  };

  return (
    <div className={`${baseStyles} ${variants[variant]} ${className}`}>
      {children}
    </div>
  );
};

export const AlertTitle = ({ children, className = '' }) => {
  return (
    <div className={`text-lg font-semibold ${className}`}>
      {children}
    </div>
  );
};

export const AlertDescription = ({ children, className = '' }) => {
  return (
    <div className={`text-sm ${className}`}>
      {children}
    </div>
  );
};