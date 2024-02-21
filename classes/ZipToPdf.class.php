<?php

class ZipToPdf {
  
    private $tmp = '../tmp/';
  
  
    public function __construct( string $pid ) {
      
      if ( !isset($_GET['zip_url']) )
        throw new Exception('No Zip URL provided!');
      
      $zip_url = urldecode( $_GET['zip_url'] );

      $this->pid = $pid;
      $this->outputPath = $this->pid.'.pdf';
      
      $saved_zip_path = $this->tmp.$this->pid.'.zip';   // Destination path
      
      // download file if we don't already have it
      if ( !file_exists($saved_zip_path) )
        $this->downloadZipFile($zip_url, $saved_zip_path);
                  
      // extract to local /tmp and (we assume) will contain a root folder named PID            
      $this->extractZip( $saved_zip_path );
      
      $images = $this->findImages( $this->tmp.$this->pid );
      $this->createPdf($images);
      $this->downloadPdf();
      
    }
    
    public function renderAjax() {
    
      echo 'hi';
      
      
    }
        
    
    private function downloadZipFile($zip_url, $saved_zip_path) {
      
      try {
      
        // $gah = file_get_contents($zip_url);
        $source = fopen($zip_url, 'r');
        
        $dest = fopen($saved_zip_path, 'w');
        // fwrite($dest, $source);
        
        if ($source && $dest) {
        
            while (!feof($source)) {
                $chunk = fread($source, 8192); // Read in chunks of 8KB
                fwrite($dest, $chunk);
            }
        
            fclose($source);
            fclose($dest);
        
        } else {
            // Handle error (e.g., file not found, permission issues)
            throw new Exception('Failed to download zip file');
        }
        
    } catch (Exception $e) {
      throw new Exception('Failed to download zip file');
    }
      
      return true;
      
    } // end of downloadZipFile
        
        
    private function extractZip($zip_path) {

        try {
            $zipFile = new PhpZip\ZipFile();
            $zipFile->openFile( $zip_path )
                    ->extractTo( $this->tmp );
            $zipFile->close();
            
            return true;
            
        } catch (PhpZip\Exception\ZipException $e) {
            // Handle error, for example, log it or return a custom error message
            error_log("Zip extraction failed: " . $e->getMessage());
            return null;
        }
    }
    

    private function findImages($path) {
      
        if (!$path)
          throw new Exception('No path provided for ZipToPdf images!');
                
        if ( !file_exists($path) )        
          throw new Exception('Provided path does not exist in ZipToPdf images!');
                
        $images = [];
        
        // Some ZIP files contains multiple REPs
        // ASSUMPTION: only one REP should contain images
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
          if ($file->isDir()) 
            continue;
          
          $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
          
          if (in_array($ext, ['jpg', 'jpeg', 'png']))
            $images[] = $file->getPathname();
        }
        
        // we need to order the images to match their intended order
        // ASSUMPTION: image filenames typically contain -001.jpg or similar numbering
        asort( $images );
        
        return $images;
        
    } // end of findImages



    private function createPdf($images) {
    $pdf = new FPDF();

    foreach ($images as $image) {
        list($width, $height) = getimagesize($image);

        // Convert dimensions from pixels to millimeters at 72 DPI
        $width_mm = $width * 25.4 / 72;
        $height_mm = $height * 25.4 / 72;

        // Determine ideal page orientation
        $orientation = ($width > $height) ? 'L' : 'P';

        // Add a new page with the exact dimensions of the image
        $pdf->AddPage($orientation, [$width_mm, $height_mm]);
        $pdf->SetMargins(0, 0, 0); // Set all margins to zero

        // Add the image to the page; it should fit exactly
        $pdf->Image($image, 0, 0, $width_mm, $height_mm);
    }

    $pdf->Output('F', $this->outputPath);
    
  } // end of createPdf



    
    private function downloadPdf() {
      
        if (file_exists($this->outputPath)) {
            // Set headers to download the file
            header('Content-Description: File Transfer');
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="'.basename($this->outputPath).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($this->outputPath));

            // Clean the output buffer
            ob_clean();
            flush();

            // Read the file and send it to the output
            readfile($this->outputPath);

            // After download, you can delete the file if it's no longer needed
            // unlink($this->outputPath);

            exit;
            
        } else {
            // Handle the error in case the file doesn't exist
            echo "File not found.";
        }
    }


} // end of class


?>