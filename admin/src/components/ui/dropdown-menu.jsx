import React, { useState, useRef, useEffect } from 'react';

export const DropdownMenu = ({ children }) => {
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef(null);

  useEffect(() => {
    const handleClickOutside = (event) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  return (
    <div ref={dropdownRef} className="relative">
      {React.Children.map(children, child =>
        React.cloneElement(child, { isOpen, setIsOpen })
      )}
    </div>
  );
};

export const DropdownMenuTrigger = ({ children, isOpen, setIsOpen, className = '' }) => {
  return (
    <button
      className={`inline-flex items-center justify-center ${className}`}
      onClick={() => setIsOpen(!isOpen)}
    >
      {children}
    </button>
  );
};

export const DropdownMenuContent = ({ children, isOpen, align = 'end', className = '' }) => {
  if (!isOpen) return null;

  const alignClasses = {
    start: 'left-0',
    end: 'right-0',
  };

  return (
    <div className={`absolute z-50 mt-2 min-w-[8rem] rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 ${alignClasses[align]} ${className}`}>
      <div className="py-1">
        {children}
      </div>
    </div>
  );
};

export const DropdownMenuItem = ({ children, onClick, disabled = false, className = '' }) => {
  return (
    <button
      className={`w-full text-left px-4 py-2 text-sm hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed ${className}`}
      onClick={onClick}
      disabled={disabled}
    >
      {children}
    </button>
  );
};
