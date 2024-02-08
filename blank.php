<?php require_once "inc_all.php";
 ?>

<!-- Breadcrumbs-->
<ol class="breadcrumb">
    <li class="breadcrumb-item">
        <a href="index.html">Dashboard</a>
    </li>
    <li class="breadcrumb-item active">Blank Page</li>
</ol>

<!-- Page Content -->
<h1>Blank Page</h1>
<hr>
<p>This is a great starting point for new custom pages.</p>

<?php

$start_date = date('Y') . "-10-10";

echo "<H1>$start_date</H1>";


?>
<br>

<?php echo randomString(100); ?>
<br>
<form id="myForm">
    <textarea id="Body" name="body" rows="4" cols="50"></textarea>
    <br>
    <button type="submit">Submit</button>
    <button type="button" id="rewordButton">Reword</button>
</form>

<script>

document.getElementById('rewordButton').addEventListener('click', function() {
    const textarea = document.getElementById('Body');
    const textToReword = textarea.value;

    // Replace 'YOUR_API_KEY' with your actual OpenAI API key
    const apiKey = '<?php echo $config_ai_api_key; ?>';
    const headers = {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${apiKey}`
    };

    // Prepare the API request payload
    const data = {
        model: "gpt-4", // or the latest available model
        prompt: `Reword the following text: "${textToReword}"`,
        temperature: 0.7,
        max_tokens: 1024,
    };

    // Make the API call to OpenAI to reword the text
    axios.post('<?php echo $config_ai_url; ?>/v1/completions', data, {headers: headers})
        .then(response => {
            textarea.value = response.data.choices[0].text.trim();
        })
        .catch(error => {
            console.error('There was an error rewording the text:', error);
        });
});

</script>

<script>toastr.success('Have Fun Wozz!!')</script>

<?php require_once "footer.php";
 ?>
