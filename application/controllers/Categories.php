<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Categories extends MY_Controller
{
    public function index(): void { $this->page(); }
    public function edit(int $id): void { $this->page($id); }
    public function store(): void { $this->requirePost(); $this->save(); }
    public function update(int $id): void { $this->requirePost(); $this->save($id); }
    public function data(): void
    {
        $this->requireAdmin(); $page=max(1,(int)$this->input->get('page',true)); $perPage=min(100,max(10,(int)$this->input->get('per_page',true)?:10)); $search=trim((string)$this->input->get('q',true));
        $this->db->start_cache()->from('categories'); if($search!==''){$this->db->group_start()->like('name',$search)->or_like('name_ar',$search)->or_like('status',$search)->group_end();}$this->db->stop_cache();$total=$this->db->count_all_results();$rows=$this->db->order_by('name')->limit($perPage,($page-1)*$perPage)->get()->result_array();$this->db->flush_cache();foreach($rows as &$row){$row['status']=lang($row['status']);$row['edit_url']=site_url('categories/edit/'.$row['id']);$row['edit_label']=lang('edit');}$this->json(true,['rows'=>$rows,'pagination'=>['page'=>$page,'per_page'=>$perPage,'total'=>$total,'total_pages'=>max(1,(int)ceil($total/$perPage))]]);
    }
    private function page(?int $id = null): void { $this->requireAdmin(); $editing = $id ? $this->db->where('id', $id)->get('categories')->row_array() : null; if ($id && !$editing) { show_404(); } $this->render('admin/simple_list', ['records'=>$this->db->order_by('name')->get('categories')->result_array(), 'resource'=>'categories', 'editing'=>$editing]); }
    private function save(?int $id = null): void { $this->requireAdmin(); $this->form_validation->set_rules('name', lang('english_name'), 'required|max_length[150]'); $this->form_validation->set_rules('name_ar', lang('arabic_name'), 'required|max_length[150]'); if (!$this->form_validation->run()) { $this->page($id); return; } $name=trim((string)$this->input->post('name',true)); $query=$this->db->where('name',$name); if ($id) { $query->where('id !=',$id); } if ($query->count_all_results('categories')) { $this->session->set_flashdata('flash',['type'=>'error','message'=>lang('category_name_taken')]); redirect($id?'categories/edit/'.$id:'categories'); } $data=['name'=>$name,'name_ar'=>trim((string)$this->input->post('name_ar',true)),'status'=>$this->input->post('status',true)===STATUS_INACTIVE?STATUS_INACTIVE:STATUS_ACTIVE]; if ($id) { $this->db->where('id',$id)->update('categories',$data); } else { $this->db->insert('categories',$data); } $this->session->set_flashdata('flash',['type'=>'success','message'=>lang('category_saved')]); redirect('categories'); }
}
