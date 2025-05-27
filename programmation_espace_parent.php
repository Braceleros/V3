<?php
// ... (avant : session, connexion PDO, $token ...)

$prog_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['programmer_histoire'])) {
    $id_histoire = intval($_POST['id_histoire']);
    $date_programmee = $_POST['date_programmee'];
    $stmt = $pdo->prepare("INSERT INTO Programmations (token_bracelet, id_histoire, date_programmee) VALUES (?, ?, ?)");
    $stmt->execute([$token, $id_histoire, $date_programmee]);
    $prog_msg = "Histoire programmée avec succès !";
}
?>
<!-- ... (HTML et <style> inchangés) ... -->
<input type="text" id="recherche-histoire" placeholder="Mot clé ou titre..." style="width:100%;padding:9px;border-radius:7px;border:1px solid #bbb;font-size:1.09em;">
<div id="resultats-histoires" style="margin-top:9px;"></div>
<div id="prog-message" style="color:#219150;font-weight:bold;"><?= $prog_msg ?? '' ?></div>
<script>
document.getElementById('recherche-histoire').addEventListener('input', function(e){
    let query = e.target.value.trim();
    let resultBox = document.getElementById('resultats-histoires');
    if(query.length < 2) { resultBox.innerHTML = ""; return; }
    fetch('ajax_recherche_histoires.php?q=' + encodeURIComponent(query))
    .then(res => res.json())
    .then(data => {
        if (!data.length) { resultBox.innerHTML = "<div style='color:#888;'>Aucune histoire trouvée.</div>"; return; }
        resultBox.innerHTML = data.map(histoire => `
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:13px;background:#f8f8fc;padding:8px 7px;border-radius:8px;box-shadow:0 2px 8px #e3eefd22">
                <img src="${histoire.image||'/images/placeholder.jpg'}" alt="" style="width:50px;height:50px;border-radius:7px;object-fit:cover;border:1px solid #dedede;">
                <div style="flex:1">
                    <b>${histoire.titre}</b><br>
                    ${histoire.audio ? `<audio controls src="${histoire.audio}" style="width:98%;margin-top:2px;"></audio>` : '<div style="color:#999;font-size:0.95em;">Pas d\'audio</div>'}
                </div>
                <button onclick="ouvrirProgrammation(${histoire.id}, '${histoire.titre.replace(/'/g,'\\\'')}')" style="background:#d10000;color:#fff;border:none;border-radius:7px;padding:9px 12px;font-weight:bold;">Programmer</button>
            </div>
        `).join('');
    });
});

function ouvrirProgrammation(id, titre) {
    let dateAuj = new Date().toISOString().slice(0,10);
    let html = `
        <div style="background:#f7f9fa;border-radius:8px;padding:14px 7px;margin-top:9px;">
            <b>Programmer l'histoire : </b> ${titre}<br>
            <form id="form-programmation">
                <input type="hidden" name="id_histoire" value="${id}">
                <label>Date de diffusion :
                    <input type="date" id="date_programmee" name="date_programmee" value="${dateAuj}" min="${dateAuj}" style="padding:6px 9px;border-radius:6px;border:1px solid #bbb;margin-top:2px;">
                </label><br>
                <button type="submit" style="margin-top:7px;background:#d10000;color:#fff;padding:7px 13px;border-radius:7px;margin-left:6px;font-size:0.98em;border:none;font-weight:bold;">Valider</button>
            </form>
        </div>
    `;
    document.getElementById('resultats-histoires').innerHTML = html;
    document.getElementById('form-programmation').onsubmit = envoyerProgrammation;
}
function envoyerProgrammation(e) {
    e.preventDefault();
    let fd = new FormData(e.target);
    fd.append('programmer_histoire', '1');
    fetch('', { method:'POST', body:fd })
    .then(res=>res.text()).then(txt=>{
        document.getElementById('prog-message').innerText="Programmation enregistrée !";
        document.getElementById('prog-message').style.display='block';
        document.getElementById('resultats-histoires').innerHTML = "";
        document.getElementById('recherche-histoire').value = "";
    });
    return false;
}
</script>