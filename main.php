<html> 
<head> 
<title>AssignLine</title> 
</head> 
<body>
	
	<script>
		function openFileOption()
		{
			document.getElementById("file1").click();
		}
	</script>


	<?php 
//		error_reporting(E_ALL);
//		ini_set('display_errors', 'On');
        require_once 'slcapiwrapper.php';
        $api = new slcAPIWrapper();

        $apiCalls = array('student', 'students', 'sections', 'attendances', 'courses', 'reportCards', 'teacher', 'parents', 'studentAssessments');

		$Picturetake = false ;
//		var_dump($_POST);
		echo '<form method="post" action="' . $_SERVER['PHP_SELF'] . '">';
        echo '<center>';
        echo '<INPUT type="submit" value="Homework" name="TakePicture"/>';
		echo '<input type="submit" value ="Time Line" name="TimeLine"/>'; 
		echo '<input type="submit" value ="Calendar" name="Calendar"/>' ;
		echo '<br>';
		$filename = $_POST["filedialog"] ;
		if (isset($_POST["SendPix"]))
		{
			echo "$filename sent <br>";
		}
         if (isset($_POST["TakePicture"]))
        {
            echo '<input type="file" id="file1" style="display:none" name="filedialog">';
           echo '<input type="text" name="class">Class<br>';
			echo '<input type="text" name="duedate">Due Date<br>';
            echo '<INPUT type="button" value="Snap Picture" name="FilePicture"onClick="openFileOption();return;">';
            echo '<br>';
            echo '<input type="submit" value ="Send Picture" name="SendPix">' ;	
        }
        else if (isset($_POST["TimeLine"]))
		{
            echo ($result = $api->getAllStudents());
		}
        else if (isset($_POST["Calendar"]))			
		{
			echo "Calendar Information";
     }

    echo '</center>';
	echo '</form>';
	?>
</body> </html>
