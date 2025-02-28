import React from "react";
import { Alert, AlertDescription, AlertTitle } from "../ui/alert";
import { Button } from '../ui/button';
import { RefreshCcw } from 'lucide-react';

interface Props {
    children: React.ReactNode
  }
  
  interface State {
    hasError: boolean
    error: Error | null
  }
  
  export class ErrorBoundary extends React.Component<Props, State> {
    constructor(props: Props) {
      super(props)
      this.state = { hasError: false, error: null }
    }
  
    static getDerivedStateFromError(error: Error) {
      return { hasError: true, error }
    }
  
    componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
      console.error("Error caught by boundary:", error, errorInfo)
    }
  
    handleReset = () => {
      this.setState({ hasError: false, error: null })
      window.location.reload()
    }
  
    render() {
      if (this.state.hasError) {
        return (
          <Alert variant="destructive" className="m-4">
            <AlertTitle>Something went wrong</AlertTitle>
            <AlertDescription>
              {this.state.error?.message || "An unexpected error occurred"}
            </AlertDescription>
            <Button
              variant="outline"
              className="mt-4"
              onClick={this.handleReset}
            >
              <RefreshCcw className="mr-2 h-4 w-4" />
              Try Again
            </Button>
          </Alert>
        )
      }
  
      return this.props.children
    }
  }